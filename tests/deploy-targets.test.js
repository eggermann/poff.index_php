const {
  normalizeDeployTargetName,
  resolveDeployTargetProfile,
  resolveTargetEnvValue,
  requireTargetEnvValue,
} = require('../scripts/deploy/deploy-targets');

describe('deploy target resolution', () => {
  test('named profiles never inherit unsuffixed values', () => {
    const env = { TEST_DEPLOY_HOST: 'wrong.example', TEST_DEPLOY_HOST_customer: 'customer.example' };
    expect(resolveTargetEnvValue(env, ['TEST_DEPLOY_HOST'], 'customer')).toBe('customer.example');
    expect(resolveTargetEnvValue(env, ['TEST_DEPLOY_HOST'], 'pageoff')).toBe('');
    expect(() => requireTargetEnvValue(env, ['TEST_DEPLOY_HOST'], 'pageoff', 'host')).toThrow('Missing required deploy setting');
  });
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

    expect(resolveDeployTargetProfile('dodi1')).toEqual({
      targetName: 'dodi1',
      suffix: '1',
      isDefault: false,
    });
  });

  test('resolves suffixed env values for secondary deploy targets', () => {
    const env = {
      host1: 'primary.example',
      user1: 'primary-user',
      destinationPath1: '/var/www/public/dodi1/',
      sourcePath1: 'pages/dodi1',
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
    expect(resolveTargetEnvValue(env, ['DEPLOY_HOST', 'SSH_HOST', 'host'], '1')).toBe('primary.example');
    expect(resolveTargetEnvValue(env, ['DEPLOY_USERNAME', 'SSH_USERNAME', 'SSH_USER', 'user'], '1')).toBe('primary-user');
    expect(resolveTargetEnvValue(env, ['DEPLOY_REMOTE_PATH', 'destinationPath'], '1')).toBe('/var/www/public/dodi1/');
    expect(resolveTargetEnvValue(env, ['DEPLOY_SOURCE_PATH', 'sourcePath'], '1')).toBe('pages/dodi1');
    expect(resolveTargetEnvValue(env, ['DEPLOY_HOST', 'SSH_HOST', 'host'], 'customer')).toBe('customer.example');
    expect(resolveTargetEnvValue(env, ['DEPLOY_USERNAME', 'SSH_USERNAME', 'SSH_USER', 'user'], 'customer')).toBe('customer-user');
    expect(resolveTargetEnvValue(env, ['DEPLOY_REMOTE_PATH', 'destinationPath'], 'customer')).toBe('/var/www/public/customer/');
  });
});
