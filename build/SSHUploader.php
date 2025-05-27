<?php
/**
 * SSH Upload functionality for the build process.
 */

class SSHUploader {
    public static function upload($configPath = null) {
        $sshUploadScript = dirname(__DIR__) . '/SSH-upload.node.js';
        
        if (!file_exists($sshUploadScript)) {
            echo "Warning: SSH-upload.node.js not found. Skipping upload.\n";
            return false;
        }

        echo "Starting SSH upload...\n";
        $command = "node " . escapeshellarg($sshUploadScript);
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "SSH upload completed successfully!\n";
            foreach ($output as $line) {
                echo $line . "\n";
            }
            return true;
        } else {
            throw new Exception("SSH upload failed with code $returnCode");
        }
    }
}