#!/usr/bin/env node
// Prüft lokale MAMP- und entfernte Web-Instanzen, schreibt Übersicht/Inventar und verteilt ausgewählte index.php-Dateien.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const os = require('os');
const {execFileSync} = require('child_process');
const {NodeSSH} = require('node-ssh');
const {loadEnv, resolveDeployPlan, resolveDeployAuth} = require('../deploy/deploy');
const {publishFile} = require('../deploy/deploy-atomic');
const {resolveDeployTargetProfile} = require('../deploy/deploy-targets');
const root = path.resolve(__dirname, '../..');
const defaultOutput = path.join(root, 'output/instances');
const quote = v => `'${String(v).replace(/'/g, `'\\''`)}'`;
function envFile() {
  const values = {};
  const file = path.join(root, '.env');
  for (const line of (fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '').split(/\r?\n/)) {
    const at = line.indexOf('=');
    if (at > 0 && !line.trim().startsWith('#')) values[line.slice(0, at).trim()] = line.slice(at + 1).trim().replace(/^['"]|['"]$/g, '');
  }
  return {...values, ...process.env};
}
function connection(server) {
  if (server === 'eggman1') {
    const file = process.env.POFF_EGGMAN1_CREDENTIALS || path.resolve(root, '../config-data/eggman1.js');
    const c = require(file);
    return {host:c.host, username:c.user, password:c.password, readyTimeout:15000};
  }
  const e = envFile();
  return {host:e.host, username:e.user, ...(e.privateKey ? {privateKeyPath:path.resolve(root,e.privateKey)} : {password:e.password}), readyTimeout:15000};
}
function digest(file) { return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex'); }
function localStatus(folder) {
  const file = path.join(folder, 'index.php');
  const folderStat = fs.statSync(folder, {throwIfNoEntry:false});
  const folderExists = folderStat?.isDirectory() ?? false;
  const indexStat = folderExists ? fs.statSync(file, {throwIfNoEntry:false}) : null;
  const indexExists = indexStat?.isFile() ?? false;
  return {
    folderExists, indexExists,
    folderModifiedAt: folderExists ? folderStat.mtime.toISOString() : null,
    resolvedFolder: folderExists ? fs.realpathSync(folder) : null,
    modifiedAt: indexExists ? indexStat.mtime.toISOString() : null,
    bytes: indexExists ? indexStat.size : null,
    sha256: indexExists ? digest(file) : null,
  };
}
async function remoteStatus(ssh, folder) {
  const program = `import os,json,hashlib,datetime\np=${JSON.stringify(folder)}\nf=os.path.join(p,'index.php')\ne=os.path.isfile(f)\nprint(json.dumps({'folderExists':os.path.isdir(p),'folderModifiedAt':datetime.datetime.fromtimestamp(os.stat(p).st_mtime,datetime.timezone.utc).isoformat() if os.path.isdir(p) else None,'indexExists':e,'modifiedAt':datetime.datetime.fromtimestamp(os.stat(f).st_mtime,datetime.timezone.utc).isoformat() if e else None,'bytes':os.path.getsize(f) if e else None,'sha256':hashlib.sha256(open(f,'rb').read()).hexdigest() if e else None}))`;
  const r=await ssh.execCommand(`python3 -c ${quote(program)}`);
  if(r.code!==0) throw new Error('Remote inspection failed');
  return JSON.parse(r.stdout);
}
function inspectDeployProfiles(env, instances) {
  return ['default', 'dodi', 'dodi1', 'dodi_2', 'pageoff', 'customer'].map(name => {
    const profile = resolveDeployTargetProfile(name);
    let plan;
    try {
      plan = resolveDeployPlan(env, profile, 'index');
    } catch (error) {
      return {name, status:'incomplete-target', detail:error.message};
    }
    const remoteFolder = path.posix.normalize(plan.remote.replace(/\/index\.php$/, '').replace(/\/+$/, ''));
    const instance = instances.find(item => item.host === plan.host && item.folder === remoteFolder);
    const auth = resolveDeployAuth(env, profile.suffix);
    const hasCredentials = Boolean(auth && (!auth.privateKeyPath || fs.existsSync(auth.privateKeyPath)));
    return {name, instance:instance?.id ?? null, host:plan.host, remote:plan.remote, source:plan.source,
      status:hasCredentials ? 'configured' : 'missing-credentials'};
  });
}
async function scan(output=defaultOutput) {
  const config=JSON.parse(fs.readFileSync(path.join(root,'build/BuildConfig.shared.json')));
  const source=path.join(root,config.pagesDir,config.siteHost,config.outputFile);
  const instances=[];
  for(const name of [config.siteHost,...config.standaloneCopyTargets,...config.additionalIndexCopyTargets.map(t=>t.name)]) {
    const folder=path.join(config.mampHtdocs,name);
    instances.push({id:'local-'+name.replace(/[^a-z0-9]/gi,'-'),name,kind:'local',role:name===config.siteHost?'Build-Ausgabe':'Lokale Installation',folder,url:`http://localhost:8888/${name}/`,eligible:true,verification:'filesystem',...localStatus(folder)});
  }
  const remote=[
    {id:'web-dominik',name:'dominikeggermann.com',server:'eggman1',host:'spica.uberspace.de',folder:'/var/www/virtual/eggman1/dominikeggermann.com',url:'https://dominikeggermann.com/',eligible:true},
    ...['dodi','dodi2','pageoff','customer'].map(name=>({id:'web-'+name,name,server:'eggman4',host:'neso.uberspace.de',folder:`/var/www/virtual/eggman4/html/pages/${name}/actual`,url:name==='pageoff'?'https://pageoff.de/':null,eligible:name!=='customer'}))
  ];
  const discovery=[];
  for(const server of ['eggman1','eggman4']) {
    const ssh=new NodeSSH();
    try {
      await ssh.connect(connection(server));
      for(const item of remote.filter(i=>i.server===server)) instances.push({...item,kind:'web',role:'Web-Installation',verification:'ssh-filesystem',...await remoteStatus(ssh,item.folder)});
      const username=connection(server).username;
      if(!/^eggman[0-9]+$/.test(username)) throw new Error('Unexpected server account');
      const r=await ssh.execCommand(`find ${quote('/var/www/virtual/'+username)} -maxdepth 5 -type f -name index.php -printf '%h\\n' 2>/dev/null`);
      for(const folder of r.stdout.split('\n').filter(Boolean)) if(!remote.some(i=>i.server===server&&i.folder===folder)) discovery.push({server,folder,classification:/bak|backup|deploy/.test(folder)?'Backup / Release-Kandidat':'Weiterer PHP-Einstieg – nicht als PageOff bestätigt',eligible:false});
    } catch(e) {
      for(const item of remote.filter(i=>i.server===server&&!instances.some(x=>x.id===i.id))) instances.push({...item,kind:'web',role:'Web-Installation',verification:'unreachable',folderExists:null,indexExists:null,modifiedAt:null,sha256:null});
    } finally {ssh.dispose();}
  }
  const sourceStatus=localStatus(path.dirname(source));
  for(const item of instances) { item.sameAsSource=item.sha256?item.sha256===sourceStatus.sha256:null; item.version=item.sha256?'sha256:'+item.sha256.slice(0,16):null; item.buildCommit=null; }
  const data={schemaVersion:1,projectRoot:root,checkedAt:new Date().toISOString(),source:{file:source,...sourceStatus,gitHead:execFileSync('git',['rev-parse','--short','HEAD'],{cwd:root,encoding:'utf8'}).trim(),versionNote:'SHA-256 identifiziert die Datei. Git-HEAD ist der Quellstand, keine nachgewiesene Build-Version.'},instances,discovery,
    profiles:inspectDeployProfiles(loadEnv(root), instances),
    personaSimulation:{status:'Konzept, noch keine Nutzerstudie',personas:[{name:'Besucherin',task:'Ein Werk finden und zum nächsten wechseln'},{name:'Künstler',task:'Foto hochladen, Layout auswählen und Quelle erhalten'},{name:'Redakteurin',task:'Remote-Work übernehmen und Herkunft erkennen'},{name:'Node-Betreiber',task:'Instanzstand prüfen und Ziel für Update auswählen'}],loop:['Aufgabe mit klaren Erfolgskriterien definieren','Lokalen Browserlauf mit Persona durchführen','Beobachtungen und Fehler als Evidenz speichern','Eine kleine Änderung vorschlagen und prüfen','Regressionen ausführen und Aufgabe wiederholen'],limits:'Simulation ersetzt keine Tests mit echten Menschen. Kein automatischer Produktionsdeploy.'}};
  fs.mkdirSync(output,{recursive:true});fs.writeFileSync(path.join(output,'instances.json'),JSON.stringify(data,null,2));
  const html=fs.readFileSync(path.join(__dirname,'instances-template.html'),'utf8').replace('/* INSTANCE_DATA */', () => JSON.stringify(data).replace(/</g,'\\u003c'));
  fs.writeFileSync(path.join(output,'index.html'),html);
  console.log(JSON.stringify({html:path.join(output,'index.html'),json:path.join(output,'instances.json'),instances:instances.length,candidates:discovery.length}));
  return data;
}
async function deploy(inventory,source,ids,apply=false) {
  const data=JSON.parse(fs.readFileSync(inventory));
  if(!source) throw new Error('--source is required: select an existing index.php explicitly');
  source=fs.realpathSync(source);
  if(path.basename(source)!=='index.php') throw new Error('Source must be index.php');
  execFileSync('php',['-l',source],{stdio:'pipe'});
  const hash=digest(source);
  const canUpdate = item => item.eligible && item.folderExists === true && item.indexExists === true;
  const selected=data.instances.filter(i=>ids==='all'?canUpdate(i):ids.split(',').includes(i.id));
  if (ids === 'all') {
    for (const item of data.instances.filter(i => i.eligible && !canUpdate(i))) {
      console.log(`SKIP ${item.id}: target missing or unverified in inventory`);
    }
  }
  if(!selected.length) throw new Error('No instances selected');
  if(ids!=='all'&&ids.split(',').some(id=>!selected.some(i=>i.id===id))) throw new Error('Unknown instance ID');
  for(const i of selected) {
    if(!/^[a-zA-Z0-9-]+$/.test(i.id)||!['local','web'].includes(i.kind))throw new Error('Invalid instance record');
    if(i.kind==='web'&&!['eggman1','eggman4'].includes(i.server))throw new Error('Unknown server');
    if(!i.eligible||i.folderExists!==true||i.indexExists!==true) throw new Error(`Unverified/missing target: ${i.id}`);
  }
  const seen=new Set();
  for(const i of selected){
    const target=path.join(i.folder,'index.php');
    const key=i.kind==='local'?fs.realpathSync(target):`${i.server}:${target}`;
    if(seen.has(key)||key===source)continue;seen.add(key);
    console.log(`${apply?'UPDATE':'PLAN'} ${i.id}: ${target} ← ${hash.slice(0,12)}`);
    if(!apply)continue;
    const stamp=Date.now()+'-'+crypto.randomBytes(6).toString('hex');
    if(i.kind==='local'){
      if(fs.lstatSync(target).isSymbolicLink())throw new Error('Refusing an index.php symlink: '+i.id);
      if(digest(target)!==i.sha256)throw new Error('Target changed since scan: '+i.id);
      const backupDir=path.join(os.homedir(),'.local/state/pageoff/backups');fs.mkdirSync(backupDir,{recursive:true,mode:0o700});
      const backup=path.join(backupDir,i.id+'-'+stamp+'.bak');
      const temp=path.join(backupDir,i.id+'-'+stamp+'.candidate.php');
      const old=fs.statSync(target);fs.copyFileSync(source,temp);fs.chmodSync(temp,old.mode&0o777);
      try{if(digest(temp)!==hash)throw new Error('Checksum mismatch');fs.copyFileSync(target,backup);fs.renameSync(temp,target);}finally{if(fs.existsSync(temp))fs.unlinkSync(temp);}
    }else{
      const ssh=new NodeSSH();
      const account=connection(i.server).username;
      if(!/^eggman[0-9]+$/.test(account))throw new Error('Unexpected server account');
      const backupDir='/var/www/virtual/'+account+'/.poff-deploy-backups';
      try{
        await ssh.connect(connection(i.server));
        const current=await remoteStatus(ssh,i.folder);if(!current.indexExists)throw new Error('Remote target disappeared');
        if(current.sha256!==i.sha256)throw new Error('Remote target changed since scan: '+i.id);
        const backup = await publishFile(ssh, { localFile: source, remoteFile: target, backupDir, expectedHash: i.sha256 });
        if (backup) console.log('Backup:', backup);
      }finally{ssh.dispose();}
    }
  }
  if(apply)console.log('Updates completed. Refresh inventory to confirm current fingerprints. Backups retained outside public webroots.');
}
async function main(){
  const args=process.argv.slice(2);const value=key=>{const at=args.indexOf(key);return at>=0?args[at+1]:undefined;};
  if(args[0]==='scan')return scan(value('--output')||defaultOutput);
  if(args[0]==='deploy')return deploy(value('--inventory')||path.join(defaultOutput,'instances.json'),value('--source'),args.includes('--all')?'all':(value('--ids')||''),args.includes('--apply'));
  console.log('node scripts/runtime/instances.js scan [--output folder]\nnode scripts/runtime/instances.js deploy --source /absolute/index.php --all|--ids id1,id2 [--apply]');
}
if(require.main===module)main().catch(e=>{console.error(e.message);process.exitCode=1;});
module.exports={scan,deploy,localStatus,inspectDeployProfiles};
