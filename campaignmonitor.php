<?php

/**
 * Prestashop Campaign Monitor Sync Module
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author     Rico Dang <rd@xport.de>
 * @copyright  2013 - 2017 xport communication GmbH
 * @link       http://www.xport.de
 */

if (!defined('_PS_VERSION_'))
  exit;

class CampaignMonitor extends Module
{
	//{{{ properties
	const DIR_VIEWS = 'views/';

	const CM_CUSTOM_FIELD_PREFIX = 'ps ';

	const REST_URL = 'https://api.createsend.com/api/v3.1/';

	public $dir_tpl;

	public $ps_version;

	public $id_shop;

	public $id_shop_group;

	public $id_lang;

	/**
	 * RESTful list url
	 * @var string
	 */
	public $listsUrl;

	/**
	 * RESTful clients url
	 * @var string
	 */
	public $clientsUrl;

	public $subscribersUrl;

	public $unsubscribersUrl;

	/**
	 * campaign monitor client id
	 * @var array
	 */
	public $id_client;

	/**
	 * have a guess...
	 * @var string
	 */
	public $clientApiKey;

	/**
	 * the address to where the CM webhook should send the response
	 * @var string
	 */
	public $webhookURI;

	public $cronUrl;

	public $cronSecureKey;

	/**
	 * complete prestashop URL including subdirectory
	 * @var string
	 */
	public $shopDomain;

	/**
	 * internal storage of the cm list id
	 * @var string
	 */
	private $id_list;

	/**
	 * the custom fields that will be exported to CM
	 * @var array
	 */
	private $customfields;

	/**
	 * CM webhook storage
	 * @var array
	 */
	private $_webHooks = array();

	/**
	 * prestashop module configuration
	 * @var object
	 */
	private $_moduleSettings;

	/**
	 * prestashop module shop specific configuration
	 * @var object
	 */
	private $_shopSettings;

	protected $_errors = array();

	protected $_conf;

	/**
	 * define default custom fields
	 * @var array
	 */
	public $customfieldsDefault = array(
		'ps_id_shop' => array(
			'fieldname' => 'Shop ID',
			'cmDatatype' => 'Number',
			'objProperty' => 'id_shop',
			'datatype' => 'int'
		),
		'ps_id_customer' => array(
			'fieldname' => 'Customer ID',
			'cmDatatype' => 'Number',
			'objProperty' => 'id',
			'datatype' => 'int'
		),
		'ps_birthday' => array(
			'fieldname' => 'Birthday',
			'cmDatatype' => 'Date',
			'objProperty' => 'birthday',
			'datatype' => 'string'
		),
		'ps_language' => array(
			'fieldname' => 'Language',
			'cmDatatype' => 'Text',
			'datatype' => 'string',
			'method' => array(
				'type' => 'static',
				'name' => 'getLangIsoCode',
				'params' => 'id',
				'returnKey' => 'iso_code'
			),
		),
		'ps_lastOrder' => array(
			'fieldname' => 'Last Order',
			'cmDatatype' => 'Date',
			'datatype' => 'string',
			'method' => array(
				'type' => 'static',
				'name' => 'getLastOrder',
				'params' => 'id',
				'returnKey' => 'date_add'
			)
		)
	);
	//}}}

	//{{{ __construct() method
	public function __construct()
	{
		$this->name    = 'campaignmonitor';
		$this->version = '0.8.4b';
		$this->tab     = 'advertising_marketing';
		$this->author  = 'xport.de';

		$this->need_instance = 0;

		parent::__construct();

		$this->displayName      = $this->l('Prestashop Campaign Monitor Sync Module');
		$this->description      = $this->l('Import and export new customers to Campaign Monitor');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall? All module settings will be removed.');
		// ps 1.6
		$this->bootstrap        = true;
		$this->ps_version       = $this->_getPsMainVersion();

		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);

		$this->id_lang       = $this->context->cookie->id_lang;
		$this->id_shop       = $this->context->shop->id;
		$this->id_shop_group = $this->context->shop->id_shop_group;
		$psDir               = $this->context->shop->physical_uri;

		$this->shopDomain = Configuration::get('PS_SHOP_DOMAIN').$psDir;
		$moduleURL        = 'http://'.$this->shopDomain.'modules/'.$this->name;
		$this->webhookURI = $moduleURL.'/webhook.php';

		// get correct template dir
		$this->dir_tpl = self::DIR_VIEWS.$this->ps_version.'/admin/getContent.tpl';

		$this->_moduleSettings = json_decode(Configuration::get(
			'CAMPAIGN_MONITOR_SETTINGS',
			null,
			$this->id_shop_group,
			$this->id_shop
		));

		if ($this->_moduleSettings)
		{
			$this->clientApiKey  = $this->_moduleSettings->clientApiKey;
			$this->id_client     = $this->_moduleSettings->id_client;
			$this->id_list       = $this->_moduleSettings->id_list;
			$this->customfields  = $this->_moduleSettings->customfields;

			$this->clientsUrl       = 'clients/'.$this->id_client;
			$this->listsUrl         = 'lists/'.$this->id_list;
			$this->subscribersUrl   = 'subscribers/';
			$this->unsubscribersUrl = $this->subscribersUrl.$this->id_list;
		}

		$this->cronSecureKey = md5($this->clientApiKey.':'.$this->id_client);
		$this->cronUrl       = $moduleURL.'/cron.php?secureKey='.$this->cronSecureKey;
	} //}}}

	//{{{ install() method
	public function install()
	{
		// Check PS version compliancy
		/*if (version_compare(_PS_VERSION_, '1.6', '>=')
			|| version_compare(_PS_VERSION_, '1.4', '<='))
		{
			$notCompliant = 'The version of your module is not compliant with your PrestaShop version.';
			$this->_errors[] = $this->l($notCompliant);
			return false;
		}*/

		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);

		if (!parent::install()
			|| !$this->registerHook('newOrder')
			|| !$this->registerHook('createAccount')
			|| !$this->registerHook('backOfficeHeader')
			|| !$this->registerHook('header')
		)
			return false;
		return true;
	} //}}}

	//{{{ uninstall() method
	public function uninstall()
	{
		$this->deleteWebHooks();

		if (!parent::uninstall()
			|| !Configuration::deleteByName('CAMPAIGN_MONITOR_SETTINGS')
		)
			return false;
		return true;
	} //}}}#

	//{{{ getContent() method
	public function getContent()
	{
		global $smarty;

		$this->_errors = array('apiInfo' => '', 'listOptions' => '');

		if ($this->ps_version == '1.6')
			$this->_clearCache($this->dir_tpl);

		if (Tools::isSubmit('saveSettings'))
		{
			$this->id_client    = Tools::getValue('cm_client_id');
			$this->clientApiKey = Tools::getValue('cm_client_api_key');

			if (empty($this->id_client))
				$this->_errors['apiInfo'][] = $this->l('Campaign Monitor client ID missing');

			if (empty($this->clientApiKey))
				$this->_errors['apiInfo'][] = $this->l('Campaign Monitor API key missing');

			if (empty($this->_errors['apiInfo']))
			{
				$moduleSettings = json_encode(array(
					'clientApiKey' => $this->clientApiKey,
					'id_client'    => $this->id_client,
					'id_list'      => '',
					'customfields' => ''
				));

 				Configuration::updateValue(
 					'CAMPAIGN_MONITOR_SETTINGS',
 					$moduleSettings,
 					false,
 					$this->id_shop_group,
 					$this->id_shop
				);

				// @todo - try to prevent double assigning this property
				$this->clientsUrl = 'clients/'.$this->id_client;

				$this->_conf = $this->l('API Info saved');
			}
		}

		if (Tools::isSubmit('saveOptions'))
		{
			$this->id_list = Tools::getValue('cm_list');

			if (empty($this->id_list))
				$this->_errors['listOptions'][] = $this->l('No campaign monitor list chosen');

			if (empty($this->_errors['listOptions']))
			{
				$customfields = array();

				if (Tools::getValue('cm_custom_fields'))
					foreach (Tools::getValue('cm_custom_fields') as $field)
						$customfields[] = array('fieldname' => $field);
				// always save shop id so we can send it to cm later
				// it is needed to identify and unsubscribe customers that subscribed via
				// the prestashop newsletter module
				$customfields[]     = array('fieldname' => 'ps_id_shop');
				$this->customfields = json_decode(json_encode($customfields));

				$this->_moduleSettings->id_list      = $this->id_list;
				$this->_moduleSettings->customfields = $this->customfields;

				$moduleSettings = json_encode($this->_moduleSettings);

				Configuration::updateValue(
 					'CAMPAIGN_MONITOR_SETTINGS',
 					$moduleSettings,
 					false,
 					$this->id_shop_group,
 					$this->id_shop
				);

				// @todo - try to prevent double assigning this property
				$this->listsUrl = 'lists/'.$this->id_list;

				$this->_conf = $this->l('List options saved');
			}
		}

		if (Tools::getValue('syncData'))
			$this->synchronise();

		if (!empty($this->id_list))
			$this->createCustomFields();

		$this->_webHooks = $this->getWebHooks(true);

		if ($this->nbrWebHooks() <= 0)
		{
			// create webhook if there is none for this list
			if (!empty($this->id_list))
			{
				// update webhook if not up to date
				if (isset($this->_webHooks[0]))
					if ($this->_webHooks[0]->Url != $this->webhookURI)
						$this->deleteWebHooks();
				$this->createWebHook();
			}
		}

		if (Tools::isSubmit('recreateWebhook'))
		{
			$this->deleteWebHooks();
			// force some delay, campaign monitor needs some seconds to execute the deletion process
			// recreation of the webhook won't work without this delay
			sleep(10);
			$this->createWebHook(true);
			$this->_webHooks = $this->getWebHooks(true);
		}

		if (Tools::isSubmit('exportToCM'))
			$this->exportCustomer('ALL', true);

		// test api connection
		$jsonObject = json_decode($this->_requestData('lists', $this->id_client));
		$cmLists    = $this->getCampaignMonitorLists(true);

		if ($jsonObject->Code == '50')
		{
			$cmLists = array();

			if (!empty($this->clientApiKey))
				$this->_errors['apiInfo'][] = $this->l('API information not correct.');
		}

		$viewCustomFieldsDefault = $this->customfieldsDefault;
		// remove shop id from optional selectable custom fields
		// shop id always needs to go to cm
		unset($viewCustomFieldsDefault['ps_id_shop']);

		$viewWebhookUrl = 'No URL set';

		if (gettype($this->_webHooks) === 'object')
		{
			if ($this->_webHooks->Code != 50 && $this->_webHooks->Code != 101)
				$viewWebhookUrl = $this->getWebHookUrl();
		}
		else
			$viewWebhookUrl = $this->getWebHookUrl();


		$smarty->assign(array(
			'cmClientApiKey'      => $this->clientApiKey,
			'cmClientID'          => $this->id_client,
			'cmSelectedList'      => $this->id_list,
			'cmLists'             => $cmLists,
			'customfieldsDefault' => $viewCustomFieldsDefault,
			'customfields'        => $this->customfields,
			'cronUrl'             => $this->cronUrl,
			'webhookUrl'          => $viewWebhookUrl,
			'webhookUrlFull'      => $this->getWebHookUrl(true),
			'errors'              => $this->_errors,
			'conf'                => $this->_conf
		));

		return $smarty->fetch(dirname(__FILE__).'/'.$this->dir_tpl);
	} //}}}

	//{{{ createWebHook() method
	/**
	 * [createWebHook description]
	 * @return bool
	 */
	public function createWebHook($skipCheck = false)
	{
		if (!$skipCheck)
			if ($this->nbrWebHooks() > 0)
				return false;

		$postData = array(
			'Events' => array('Subscribe', 'Update', 'Deactivate'),
			'Url' => $this->webhookURI,
			'PayloadFormat' => 'json'
		);

		if ($this->_requestData('lists', 'webhooks', false, 'POST', $postData))
			return true;
		return false;
	} //}}}

	//{{{ nbrWebHooks() methods
	/**
	 * counts all active cm webhooks
	 * @return integer, amount of webhooks
	 */
	public function nbrWebHooks()
	{
		if (sizeof($this->_webHooks) && !isset($this->_webHooks->Code))
			return sizeof($this->_webHooks);
		return 0;
	} //}}}

	//{{{ deleteWebHooks() methods
	public function deleteWebHooks()
	{
		/* if ($this->nbrWebHooks() <= 0)
			return false; */

		$webhookDeleteUrl = $this->listsUrl.'/webhooks';

		foreach ($this->_webHooks as $webhooks)
		{
			$id_webhook = $webhooks->WebhookID;
			$this->_requestData($webhookDeleteUrl, $id_webhook, false, 'DELETE');
		}
	} //}}}

	//{{{ exportCustomer() method
	/**
	 * [exportCustomer description]
	 * @param  string $customers    customers to export - 'LAST', 'ALL' or customer id
	 * @return void                 [description]
	 */
	public function exportCustomer($customers = 'LAST', $resubscribe = false)
	{
		// add or update specific customer
		if (is_numeric($customers))
		{
			$customer   = new Customer($customers);
			$subscriber = $this->prepareSubscriber($customer->id, $resubscribe);

			if ($subscriber !== false)
				$this->addSubscribers($subscriber);
		}
		else if ($customers == 'LAST')
		{
			$lastCustomer = self::getLastCustomer();
			$customer     = new Customer($lastCustomer['id_customer']);
			$subscriber   = $this->prepareSubscriber($customer->id, $resubscribe);

			if ($subscriber !== false)
				$this->addSubscribers($subscriber);
		}
		else if ($customers == 'ALL')
		{
			foreach (Customer::getCustomers() as $customer)
			{
				$subscriber = $this->prepareSubscriber($customer['id_customer'], $resubscribe);

				if ($subscriber !== false)
					$this->addSubscribers($subscriber);
			}
			// check for those who subscribed via the prestashop newsletter module
			foreach (self::getBlockNewsletterSubscribers() as $subscriber)
			{
				$customfields[] = array(
					'Key' => self::CM_CUSTOM_FIELD_PREFIX.$this->customfieldsDefault['ps_id_shop']['fieldname'],
					'Value' => (int)$subscriber['id_shop']
				);
				$this->addSubscribers(array(
					'EmailAddress' => $subscriber['email'],
					'CustomFields' => $customfields
				));
			}
		}
	} //}}}

	//{{{ updateCustomer() method
	/**
	 * [updateCustomer description]
	 * @param  int    $id_customer [description]
	 * @param  string $action      resubscribe, unsubscribe or delete
	 * @return [type]              [description]
	 */
	public function updateCustomer($id_customer, $action = 'resubscribe')
	{
		// add or update specific customer
		if (empty($action) || !is_numeric($id_customer))
			return false;

		$customer = new Customer($id_customer);

		if ($action == 'resubscribe')
		{
			$subscriber = $this->prepareSubscriber($customer->id, true);

			if ($subscriber !== false)
				$this->updateSubscribers($subscriber);
		}
		else if ($action == 'unsubscribe')
			$this->unsubscribe($customer->email);

		/* @todo mark as deleted in campaign monitor
		   if customer is inactive or deleted in prestashop */
		else if ($action == 'delete') {}
	} //}}}

	//{{{ createCustomFields() method
	public function createCustomField($fieldname, $datatype)
	{
		$postData = array(
			'FieldName' => $fieldname,
			'DataType' => $datatype,
			'Options' => array(),
			'VisibleInPreferenceCenter' => true
		);

		if ($this->_requestData('lists', 'customfields', false, 'POST', $postData))
			return true;
		return false;
	} //}}}

	//{{{ createCustomFields() method
	public function createCustomFields()
	{
		if (!$this->customfields)
			return false;

		foreach ($this->customfields as $value)
		{
			$cfData    = $this->customfieldsDefault[$value->fieldname];
			$fieldname = self::CM_CUSTOM_FIELD_PREFIX.$cfData['fieldname'];

			if ($this->customFieldExists($fieldname))
				continue;

			$this->createCustomField($fieldname, $cfData['cmDatatype']);
		}
	} //}}}

	//{{{ customFieldExists() method
	public function customFieldExists($fieldname)
	{
		foreach ($this->getCustomFields(true) as $customfield)
			if (isset($customfield->FieldName) && $customfield->FieldName == $fieldname)
				return true;
		return false;
	} //}}}

	//{{{ prepareCustomFields() method
	public function prepareCustomFields($id_customer)
	{
		$customer     = new Customer($id_customer);
		$customfields = array();
		$i            = 0;

		foreach ($this->customfields as $value)
		{
			$cfData                  = $this->customfieldsDefault[$value->fieldname];
			$datatype                = $cfData['datatype'];
			$customfields[$i]['Key'] = self::CM_CUSTOM_FIELD_PREFIX.$cfData['fieldname'];

			if (isset($cfData['objProperty']))
				$customfields[$i]['Value'] = $customer->{$cfData['objProperty']};

			else if (isset($cfData['method']))
			{
				$params     = $customer->{$cfData['method']['params']};
				$methodName = $cfData['method']['name'];
				$method     = $this->{$methodName}($params);

				if ($cfData['method']['type'] == 'static')
					$method = call_user_func(array('self', $methodName), $params);

				$customfields[$i]['Value'] = $method[$cfData['method']['returnKey']];

				if ($cfData['cmDatatype'] == 'Date')
					$customfields[$i]['Value'] = substr($customfields[$i]['Value'], 0, 10);
			}
			settype($customfields[$i]['Value'], $datatype);
			$i++;
		}
		return $customfields;
	} //}}}

	//{{{ addSubscribers() method
	public function addSubscribers($postData, $update = false)
	{
		$method = 'POST';
		$params = null;

		if ($update)
		{
			$method = 'PUT';
			$params = array('email' => $postData['EmailAddress']);
		}

		if ($this->_requestData(
			'subscribers', $this->id_list, false, $method, $postData, $params
		))
			return true;
		return false;
	} //}}}

	//{{{ updateSubscriber() method
	public function updateSubscribers($postData)
	{
		$this->addSubscribers($postData, true);
	} //}}}

	//{{{ unsubscribe()) method
	public function unsubscribe($emailAddress)
	{
		$postData = array(
			'EmailAddress' => $emailAddress
		);

		if ($this->_requestData('unsubscribers', 'unsubscribe', false, 'POST', $postData))
			return true;
		return false;
	} //}}}

	//{{{ prepareSubscriber() method
	/**
	 * [prepareSubscriber description]
	 * @param  integer     $id_customer
	 * @return array|bool  either array with subscriber data or false
	 */
	public function prepareSubscriber($id_customer, $resubscribe = false)
	{
		$customer = new Customer($id_customer);

		if ($customer->newsletter)
		{
			return array(
				'EmailAddress' => $customer->email,
				'Name' => $customer->firstname.' '.$customer->lastname,
				'CustomFields' => $this->prepareCustomFields($customer->id),
				'Resubscribe' => $resubscribe,
				'RestartSubscriptionBasedAutoresponders' => true
			);
		}
		return false;
	} //}}}

	//{{{ _requestData() method
	/**
	 * [_requestData description]
	 * @param  [type]  $type     [description]
	 * @param  [type]  $file     [description]
	 * @param  boolean $object   [description]
	 * @param  string  $method   [description]
	 * @param  [type]  $postData [description]
	 * @return [type]            [description]
	 */
	private function _requestData($type, $file, $object = false,
	                              $method = 'GET', $postData = null,
	                              $params = null, $verbose = false)
	{
		if (isset($this->{$type.'Url'}))
			$curlOptUrl = $this->{$type.'Url'};
		else
			$curlOptUrl = $type;

		$curlOptUrl = self::REST_URL.$curlOptUrl.'/'.$file.'.json';

		if ($params)
			$curlOptUrl .= '?'.http_build_query($params);

		$curl = curl_init();

		$curlOptArray =  array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_USERPWD        => $this->clientApiKey.':',
			CURLOPT_URL            => $curlOptUrl
		);

		if ($method == 'POST')
			$curlOptArray[CURLOPT_POST] = true;

		if ($method == 'POST' || $method == 'PUT')
		{
			if (is_object($postData) || is_array($postData))
				$postData = json_encode($postData);

			$curlOptArray[CURLOPT_POSTFIELDS] = $postData;
		}

		curl_setopt_array($curl, $curlOptArray);

		$json = curl_exec($curl);

		return $object ? json_decode($json) : $json;
	} //}}}

	//{{{ getCampaignMonitorLists() method
	public function getCampaignMonitorLists($object = false)
	{
		return $this->_requestData('clients', 'lists', $object);
	} //}}}

	//{{{ getCustomFields() method
	public function getCustomFields($object = false)
	{
		return $this->_requestData('lists', 'customfields', $object);
	} //}}}

	//{{{ getCustomFieldValue() method
	/**
	 * get custom field value by key
	 * @param  [string] $key          - valid custom field key
	 * @param  [array]  $customfields - fields array, https://www.campaignmonitor.com/api/subscribers/#adding_a_subscriber
	 * @return [mixed]
	 */
	public function getCustomFieldValue($key, $customfields)
	{
		$customfield = null;
		foreach ($customfields as $field)
		{
			$field = (object)$field;
			if ($field->Key == $key)
			{
				$customfield = $field->Value;
				break;
			}
		}
		return $customfield;
	} //}}}

	//{{{ getSubscribers() method
	public function getSubscribers($object = false, $resultsOnly = false)
	{
		$subscribers  = array();
		$active       = $this->_requestData('lists', 'active', $object);
		$unsubscribed = $this->_requestData('lists', 'unsubscribed', $object);
		$deleted      = $this->_requestData('lists', 'deleted', $object);

		if ($resultsOnly)
		{
			return array_merge(
				$active->Results,
				$unsubscribed->Results,
				$deleted->Results
			);
		}

		return array(
			'active'       => $active,
			'unsubscribed' => $unsubscribed,
			'deleted'      => $deleted
		);
	} //}}}

	//{{{ synchronise() methods
	public function synchronise()
	{
		$stateArray = array(
			'Active' => 1,
			'Deleted' => 0,
			'Unsubscribed' => 0
		);
		$customers = array();

		foreach ($this->getSubscribers(true, true) as $subscriber)
		{
			$customer = new Customer;
			$customer = $customer->getByEmail($subscriber->EmailAddress);

			if (!$customer)
				continue;

			$psSubscribed = (int)$customer->newsletter;
			$cmSubscribed = (int)$stateArray[$subscriber->State];

			/* resubscribe if unsubscribed in campaign monitor
			   but subscribed in prestashop */
			if ($psSubscribed && !$cmSubscribed)
				$this->updateCustomer($customer->id, 'resubscribe');

			/* unsubscribe if subscribed in campaign monitor
			   but unsubscribed in prestashop */
			else if (!$psSubscribed && $cmSubscribed)
				$this->updateCustomer($customer->id, 'unsubscribe');

			/* @todo mark as deleted in campaign monitor
			   if inactive or deleted in prestashop */
			// else
		}
	} //}}}

	//{{{ getWebHooks() methods
	public function getWebHooks($object = false)
	{
		return $this->_requestData('lists', 'webhooks', $object);
	} //}}}

	//{{{ getLastOrder()
	public static function getLastOrder($id_customer)
	{
		$customerOrders = Order::getCustomerOrders($id_customer);
		return reset($customerOrders);
	} //}}}

	//{{{ getLastCustomer()
	public static function getLastCustomer()
	{
		$customers = Customer::getCustomers();
		return end($customers);
	} //}}}

	//{{{ getLangIsoCode()
	public static function getLangIsoCode($id_customer)
	{
		$customer = new Customer($id_customer);
		return array('iso_code' => Language::getIsoById($customer->id_lang));
	} //}}}

	//{{{ getBlockNewsletterSubscribers() method
	/**
	 * get subscribers that subscribed via the prestashop newsletter module
	 * @return [type] [description]
	 */
	public static function getBlockNewsletterSubscribers()
	{
		$dbquery = new DbQuery();
		$dbquery->select('*');
		$dbquery->from('newsletter', 'n');
		$dbquery->where('n.`active` = 1');
		return Db::getInstance()->executeS($dbquery->build());
	}
	//}}}

	//{{{ unregisterFromPsNewsletter() method
	/**
	 * slightly modified version of the original Blocknewsletter unregister method
	 *
	 * @param  [string] $email
	 * @param  [int] $register_status
	 * @param  [int] $id_shop
	 * @return [bool]
	 */
	public static function unregisterFromPsNewsletter($email, $register_status, $id_shop = null)
	{
		if ($register_status == Blocknewsletter::GUEST_REGISTERED)
			$sql = 'DELETE FROM '._DB_PREFIX_.'newsletter WHERE `email` = \''.pSQL($email).'\' AND id_shop = '.(int)$id_shop;
		else if ($register_status == Blocknewsletter::CUSTOMER_REGISTERED)
			$sql = 'UPDATE '._DB_PREFIX_.'customer SET `newsletter` = 0 WHERE `email` = \''.pSQL($email).'\' AND id_shop = '.(int)$id_shop;

		if (!isset($sql) || !Db::getInstance()->execute($sql))
			return false;
		return true;
	} //}}}

	//{{{ hookNewOrder() method
	public function hookNewOrder($params)
	{
		$this->exportCustomer('LAST', true);
	} //}}}

	//{{{ hookCreateAccount() method
	public function hookCreateAccount($params)
	{
		$this->hookNewOrder($params);
	} //}}}

	//{{{ hookBackOfficeHeader() method
	public function hookBackOfficeHeader($params)
	{
		$cssFile = _MODULE_DIR_.$this->name.'/css/'.$this->name.'.css';
		return '<link href="'.$cssFile.'" rel="stylesheet" type="text/css" />';
	} //}}}

	//{{{ hookHeader() method
	/**
	 * send subscribers which subscribed via prestashop newsletter module to cm
	 */
	public function hookHeader($params)
	{
		if (Tools::isSubmit('submitNewsletter'))
		{
			$customfields   = array();
			$customfields[] = array(
				'Key' => self::CM_CUSTOM_FIELD_PREFIX.$this->customfieldsDefault['ps_id_shop']['fieldname'],
				'Value' => (int)$this->id_shop
			);
			$this->addSubscribers(array(
				'EmailAddress' => Tools::getValue('email'),
				'CustomFields' => $customfields
			));
		}
	} //}}}

	//{{{ _getPsMainVersion() method
	public function _getPsMainVersion()
	{
		return substr(_PS_VERSION_, 0, 3);
	} //}}}

	//{{{ getWebHookUrl() method
	public function getWebHookUrl($full = false)
	{
		if ($this->_webHooks->Code == 50 || !is_array($this->_webHooks))
			return false;

		if (isset($this->_webHooks[0]->Url))
		{
			$url = $this->_webHooks[0]->Url;
			if ($full)
				return $url;
			return parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).'/';
		}
		return false;
	} //}}}

}
