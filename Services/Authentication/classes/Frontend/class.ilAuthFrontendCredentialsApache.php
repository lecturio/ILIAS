<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Authentication/classes/Frontend/class.ilAuthFrontendCredentials.php';
include_once './Services/Authentication/interfaces/interface.ilAuthCredentials.php';

/**
 * Description of class class 
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de> 
 *
 */
class ilAuthFrontendCredentialsApache extends ilAuthFrontendCredentials implements ilAuthCredentials
{
	private $settings = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		include_once './Services/Administration/classes/class.ilSetting.php';
		$this->settings = new ilSetting('apache_auth');
	}

	/**
	 * @return \ilSetting
	 */
	protected function getSettings()
	{
		return $this->settings;
	}

	/**
	 * Init credentials from request
	 */
	public function initFromRequest()
	{
		$this->getLogger()->dump($_SERVER, ilLogLevel::DEBUG);
		$this->getLogger()->debug($this->getSettings()->get('apache_auth_username_direct_mapping_fieldname', ''));

		include_once './Services/AuthApache/classes/class.ilAuthProviderApache.php';

		switch($this->getSettings()->get('apache_auth_username_config_type'))
		{
			case ilAuthProviderApache::APACHE_AUTH_TYPE_DIRECT_MAPPING:
				if(array_key_exists($this->getSettings()->get('apache_auth_username_direct_mapping_fieldname'), $_SERVER))
				{
					$this->setUsername($_SERVER[$this->getSettings()->get('apache_auth_username_direct_mapping_fieldname', '')]);
				}
				break;

			case ilAuthProviderApache::APACHE_AUTH_TYPE_BY_FUNCTION:
				include_once 'Services/AuthApache/classes/custom_username_func.php';
				$this->setUsername(ApacheCustom::getUsername());
				break;
		}
	}

	/**
	 * @return bool
	 */
	public function hasValidTargetUrl()
	{
		if(!isset($_GET['r']) || 0 == strlen(trim($_GET['r'])))
		{
			return false;
		}

		$url = trim($_GET['r']);

		$validDomains = array();
		$path         = ILIAS_DATA_DIR . '/' . CLIENT_ID . '/apache_auth_allowed_domains.txt';
		if(file_exists($path) && is_readable($path))
		{
			foreach(file($path) as $line)
			{
				if(trim($line))
				{
					$validDomains[] = trim($line);
				}
			}
		}

		$urlParts       = parse_url($url);
		$redirectDomain = $urlParts['host'];

		$validRedirect = false;
		foreach($validDomains as $validDomain)
		{
			if($redirectDomain === $validDomain)
			{
				$validRedirect = true;
				break;
			}

			if(strlen($redirectDomain) > (strlen($validDomain) + 1))
			{
				if(substr($redirectDomain, (0 - strlen($validDomain) - 1)) === '.' . $validDomain)
				{
					$validRedirect = true;
					break;
				}
			}
		}

		return $validRedirect;
	}

	/**
	 * @return string
	 */
	public function getTargetUrl()
	{
		return ilUtil::appendUrlParameterString(trim($_GET['r']), 'passed_sso=1');
	}
}