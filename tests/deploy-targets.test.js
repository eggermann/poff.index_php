const {
  normalizeDeployTargetName,
  resolveDeployTargetProfile,
  resolveTargetEnvValue,
} = require('../scripts/deploy-targets');

describe('deploy target resolution', () => {
  test('normalizes target names', () => {
    expect(normalizeDeployTargetName(' Dodi 2 ')).toBe('dodi_2');
    expect(normalizeDeployTargetName('')).toBe('default');
  });

  test('resolves the base profile without a numeric suffix', () => {
    expect(resolveDeployTargetProfile('dodi')).toEqual({
      targetName: 'dodi',
      suffix: '',
      isDefault: false,
    });
  });

  test('resolves named page targets as suffix profiles', () => {
    expect(resolveDeployTargetProfile('customer')).toEqual({
      targetName: 'customer',
      suffix: 'customer',
      isDefault: false,
    });

    expect(resolveDeployTargetProfile('pageoff')).toEqual({
      targetName: 'pageoff',
      suffix: 'pageoff',
      isDefault: false,
    });
  });

  test('resolves suffixed env values for secondary deploy targets', () => {
    const env = {
      host2: 'secondary.example',
      user2: 'secondary-user',
      destinationPath2: '/var/www/public/dodi_2/',
      sourcePath2: 'pages/dodi_2',
      hostcustomer: 'customer.example',
      usercustomer: 'customer-user',
      destinationPathcustomer: '/var/www/public/customer/',
    };

    expect(resolveTargetEnvValue(env, ['DEPLOY_HOST', 'SSH_HOST', 'host'], '2')).toBe('secondary.example');
    expect(resolveTargetEnvValue(env, ['DEPLOY_USERNAME', 'SSH_USERNAME', 'SSH_USER', 'user'], '2')).toBe('secondary-user');
    expect(resolveTargetEnvValue(env, ['DEPLOY_REMOTE_PATH', 'destinationPath'], '2')).toBe('/var/www/public/dodi_2/');
    expect(resolveTargetEnvValue(env, ['DEPLOY_SOURCE_PATH', 'sourcePath'], '2')).toBe('pages/dodi_2');
    expect(resolveTargetEnvValue(env, ['DEPLOY_HOST', 'SSH_HOST', 'host'], 'customer')).toBe('customer.example');
    expect(resolveTargetEnvValue(env, ['DEPLOY_USERNAME', 'SSH_USERNAME', 'SSH_USER', 'user'], 'customer')).toBe('customer-user');
    expect(resolveTargetEnvValue(env, ['DEPLOY_REMOTE_PATH', 'destinationPath'], 'customer')).toBe('/var/www/public/customer/');
  });
});
