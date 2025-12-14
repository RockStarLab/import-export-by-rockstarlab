const { spawn } = require('child_process');
const path = require('path');
const os = require('os');

// Define standard system paths for different operating systems
function getSystemPaths() {
  const platform = os.platform();
  
  // Base paths for all systems
  let systemPaths = [];
  
  if (platform === 'win32') {
    // Windows paths
    systemPaths = [
      'C:\\Windows\\system32',
      'C:\\Windows',
      'C:\\Windows\\System32\\Wbem',
      'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\',
      'C:\\Program Files\\nodejs\\',
      'C:\\Program Files\\Git\\cmd',
      'C:\\Program Files\\php\\',
      // Add paths for 32-bit systems
      'C:\\Program Files (x86)\\nodejs\\',
      'C:\\Program Files (x86)\\Git\\cmd',
      'C:\\Program Files (x86)\\php\\',
      // Common user-installed PHP locations
      'C:\\php',
      'C:\\xampp\\php',
      'C:\\wamp\\bin\\php\\php*',
      'C:\\laragon\\bin\\php\\php*',
    ];
    
    // Add potential Windows Subsystem for Linux paths
    if (os.release().toLowerCase().includes('microsoft')) {
      systemPaths.push('/usr/local/bin', '/usr/bin', '/bin');
    }
  } else {
    // Unix-like systems (Linux, macOS)
    systemPaths = [
      '/usr/local/bin',
      '/usr/bin',
      '/bin',
      '/usr/sbin',
      '/sbin',
      '/usr/local/php/bin',
      '/usr/local/nodejs/bin',
      '/opt/homebrew/bin', // Common Homebrew path on M1 Macs
      '/opt/homebrew/sbin',
      '/opt/homebrew/opt/php/bin',
      '/opt/homebrew/opt/node/bin',
      '/opt/homebrew/opt/yarn/bin',
      '/usr/local/opt/php/bin',
      '/usr/local/opt/node/bin',
      '/usr/local/opt/yarn/bin',
    ];
    
    // Add specific Linux directories
    if (platform === 'linux') {
      systemPaths.push(
        '/usr/local/php*',
        '/opt/php*',
        '/usr/lib/php*',
        '/snap/bin', // For Snap packages on Ubuntu
        '/var/lib/snapd/snap/bin'
      );
    }
    
    // Add paths to user's home directory for global npm packages
    const homeDir = os.homedir();
    if (homeDir) {
      // Add all NVM versions - this is important for Node.js version managers
      try {
        const fs = require('fs');
        const nvmPath = path.join(homeDir, '.nvm/versions/node');
        if (fs.existsSync(nvmPath)) {
          const nodeVersions = fs.readdirSync(nvmPath);
          nodeVersions.forEach(version => {
            systemPaths.push(path.join(nvmPath, version, 'bin'));
          });
        }
      } catch (e) {
        // If can't read NVM directory, just add default path
        systemPaths.push(path.join(homeDir, '.nvm/versions/node/*/bin'));
      }
      
      systemPaths.push(path.join(homeDir, '.yarn/bin'));
      systemPaths.push(path.join(homeDir, '.config/yarn/global/node_modules/.bin'));
      systemPaths.push(path.join(homeDir, 'AppData/Roaming/npm'));
      systemPaths.push(path.join(homeDir, 'AppData/Roaming/Composer/vendor/bin'));
      systemPaths.push(path.join(homeDir, '.composer/vendor/bin')); // Unix Composer path
    }
  }
  
  return systemPaths;
}

module.exports = {
  hooks: {
    'pre-commit': (args) => {
      const systemPaths = getSystemPaths().join(path.delimiter);
      // Add system paths to the beginning of PATH to give them priority
      process.env.PATH = `${systemPaths}${path.delimiter}${process.env.PATH}`;
      
      // For debugging
      console.log('PATH:', process.env.PATH);
      
      return spawn('lint-staged', args, { stdio: 'inherit' });
    }
  },
  // Export getSystemPaths to be used in shell scripts
  getSystemPaths
};
