const { flipFuses, FuseVersion, FuseV1Options } = require('@electron/fuses');

module.exports = async function afterPack({ appOutDir, packager }) {
  const platform = packager.platform.nodeName;
  if (platform !== 'win32') return;

  const exeName = packager.appInfo.productFilename + '.exe';
  const exePath = require('path').join(appOutDir, exeName);

  await flipFuses(exePath, {
    version: FuseVersion.V1,
    [FuseV1Options.RunAsNode]: false,
    [FuseV1Options.EnableEmbeddedAsarIntegrityValidation]: false,
    [FuseV1Options.OnlyLoadAppFromAsar]: false,
  });

  console.log('[afterPack] Disabled ASAR integrity check in', exeName);
};
