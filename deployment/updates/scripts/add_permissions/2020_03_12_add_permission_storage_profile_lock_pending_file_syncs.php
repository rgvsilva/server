<?php
/**
 * @package deployment
 * Add permissions to storageProfile lockPendingFileSyncs
 */

$script = realpath(dirname(__FILE__) . '/../../../../') . '/alpha/scripts/utils/permissions/addPermissionsAndItems.php';
$config = realpath(dirname(__FILE__)) . '/../../../permissions/service.storageprofile.ini';
passthru("php $script $config");