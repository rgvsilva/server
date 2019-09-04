<?php
/**
 * @package plugins.sso
 * @subpackage Admin
 */
class SsoProfileSetStatusAction extends KalturaApplicationPlugin
{
	/**
	 * @return string - absolute file path of the phtml template
	 */
	public function getTemplatePath()
	{
		return realpath(dirname(__FILE__));
	}

	/**
	 * @param Zend_Controller_Action $action
	 * @throws Infra_Exception
	 */
	public function doAction(Zend_Controller_Action $action)
	{
		$action->getHelper('layout')->disableLayout();
		$ssoProfileId = $this->_getParam('ssoProfileId');
		$newStatus = $this->_getParam('ssoProfileStatus');
		$partnerId = $this->_getParam('partnerId');

		$client = Infra_ClientHelper::getClient();
		$ssoPluginClient = Kaltura_Client_Sso_Plugin::get($client);
		Infra_ClientHelper::impersonate($partnerId);
		try
		{
			if  ( $newStatus == Kaltura_Client_Sso_Enum_SsoStatus::DELETED )
				$res = $ssoPluginClient->sso->delete($ssoProfileId);
			else
				$res = $ssoPluginClient->sso->update($ssoProfileId, $newStatus);
			echo $action->getHelper('json')->sendJson('ok', false);
		} catch (Exception $e)
		{
			KalturaLog::err($e->getMessage() . "\n" . $e->getTraceAsString());
			echo $action->getHelper('json')->sendJson($e->getMessage(), false);
		}
		Infra_ClientHelper::unimpersonate();
	}
}