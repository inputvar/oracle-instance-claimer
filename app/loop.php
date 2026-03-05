<?php
declare(strict_types=1);

$logFile = '/var/log/oci-loop.log';

function logMsg(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Hitrov\Exception\ApiCallException;
use Hitrov\FileCache;
use Hitrov\OciApi;
use Hitrov\OciConfig;
use Hitrov\TooManyRequestsWaiter;
use Hitrov\Notification\Email;

// Load .env if it exists (on Fly.io, env vars come from fly secrets)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createUnsafeImmutable(__DIR__);
    $dotenv->safeLoad();
}

$emailNotifier = new Email();

logMsg('OCI ARM Instance Claimer loop started', $logFile);
logMsg('Region: ' . getenv('OCI_REGION') . ' | Shape: ' . getenv('OCI_SHAPE') . ' | OCPUs: ' . getenv('OCI_OCPUS') . ' | Memory: ' . getenv('OCI_MEMORY_IN_GBS') . 'GB', $logFile);

$attempt = 0;

while (true) {
    $attempt++;
    logMsg("--- Attempt #$attempt ---", $logFile);

    try {
        $config = new OciConfig(
            getenv('OCI_REGION'),
            getenv('OCI_USER_ID'),
            getenv('OCI_TENANCY_ID'),
            getenv('OCI_KEY_FINGERPRINT'),
            getenv('OCI_PRIVATE_KEY_FILENAME'),
            getenv('OCI_AVAILABILITY_DOMAIN') ?: null,
            getenv('OCI_SUBNET_ID'),
            getenv('OCI_IMAGE_ID'),
            (int) getenv('OCI_OCPUS'),
            (int) getenv('OCI_MEMORY_IN_GBS')
        );

        $bootVolumeSizeInGBs = (string) getenv('OCI_BOOT_VOLUME_SIZE_IN_GBS');
        $bootVolumeId = (string) getenv('OCI_BOOT_VOLUME_ID');
        if ($bootVolumeSizeInGBs) {
            $config->setBootVolumeSizeInGBs($bootVolumeSizeInGBs);
        } elseif ($bootVolumeId) {
            $config->setBootVolumeId($bootVolumeId);
        }

        $api = new OciApi();
        if (getenv('CACHE_AVAILABILITY_DOMAINS')) {
            $api->setCache(new FileCache($config));
        }
        if (getenv('TOO_MANY_REQUESTS_TIME_WAIT')) {
            $api->setWaiter(new TooManyRequestsWaiter((int) getenv('TOO_MANY_REQUESTS_TIME_WAIT')));
        }

        $shape = getenv('OCI_SHAPE');
        $maxRunningInstancesOfThatShape = 1;
        if (getenv('OCI_MAX_INSTANCES') !== false) {
            $maxRunningInstancesOfThatShape = (int) getenv('OCI_MAX_INSTANCES');
        }

        $instances = $api->getInstances($config);
        $existingInstances = $api->checkExistingInstances($config, $instances, $shape, $maxRunningInstancesOfThatShape);
        if ($existingInstances) {
            logMsg("Already have running instance(s): $existingInstances", $logFile);
            logMsg('SUCCESS: Instance already exists. Exiting loop.', $logFile);
            if ($emailNotifier->isSupported()) {
                $emailNotifier->notify("OCI Instance Already Running\n\n$existingInstances");
            }
            exit(0);
        }

        if (!empty($config->availabilityDomains)) {
            if (is_array($config->availabilityDomains)) {
                $availabilityDomains = $config->availabilityDomains;
            } else {
                $availabilityDomains = [$config->availabilityDomains];
            }
        } else {
            $availabilityDomains = $api->getAvailabilityDomains($config);
        }

        foreach ($availabilityDomains as $availabilityDomainEntity) {
            $availabilityDomain = is_array($availabilityDomainEntity) ? $availabilityDomainEntity['name'] : $availabilityDomainEntity;
            try {
                logMsg("Trying availability domain: $availabilityDomain", $logFile);
                $instanceDetails = $api->createInstance($config, $shape, getenv('OCI_SSH_PUBLIC_KEY'), $availabilityDomain);

                // SUCCESS - instance created!
                $message = json_encode($instanceDetails, JSON_PRETTY_PRINT);
                logMsg("SUCCESS: Instance created!\n$message", $logFile);
                if ($emailNotifier->isSupported()) {
                    $emailNotifier->notify("OCI ARM Instance Created Successfully!\n\n$message");
                }
                exit(0);

            } catch (ApiCallException $e) {
                $message = $e->getMessage();

                if (
                    $e->getCode() === 500 &&
                    strpos($message, 'InternalError') !== false &&
                    strpos($message, 'Out of host capacity') !== false
                ) {
                    logMsg("Out of host capacity in $availabilityDomain - will retry", $logFile);
                    sleep(16);
                    continue;
                }

                // Other API error - likely config issue
                logMsg("ERROR: API error: $message", $logFile);
                if ($emailNotifier->isSupported()) {
                    $emailNotifier->notify("OCI Claimer - API Error (will keep retrying)\n\n$message");
                }
                // Don't exit, sleep longer and retry
                break;
            }
        }

    } catch (\Throwable $e) {
        logMsg("ERROR: Uncaught exception: " . $e->getMessage(), $logFile);
        logMsg("Stack trace: " . $e->getTraceAsString(), $logFile);
        // NEVER exit on uncaught exceptions
    }

    logMsg("Sleeping 60 seconds before next attempt...", $logFile);
    sleep(60);
}
