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
 * @copyright  2013 - 2016 xport communication GmbH
 * @link       http://www.xport.de
 */

require_once(dirname(__FILE__).'/../../config/config.inc.php');

$json    = file_get_contents('php://input');
$jsonObj = json_decode($json);

// default shop context
if (Shop::isFeatureActive())
	Shop::setContext(Shop::CONTEXT_ALL);

if (is_object($jsonObj))
{
	$campaignmonitor = Module::getInstanceByName('campaignmonitor');
	$blocknewsletter = Module::getInstanceByName('blocknewsletter');
	foreach ($jsonObj->Events as $event)
	{
		$emailAddress   = $event->EmailAddress;
		$customerByMail = Customer::getCustomersByEmail($emailAddress);
		if ($customerByMail)
		{
			$customer = new Customer($customerByMail[0]['id_customer']);
			if ($event->Type == 'Deactivate')
				$customer->newsletter = false;
			if ($event->Type == 'Subscribe' || $event->Type == 'Update')
				$customer->newsletter = true;
			$customer->save();
		}
		else
		{
			$id_shop = null;
			// set shop context for each subscriber
			if (Shop::isFeatureActive())
			{
				$id_shop = $campaignmonitor->getCustomFieldValue(
					'ps Shop ID',
					$event->CustomFields
				);
				Shop::setContext(Shop::CONTEXT_SHOP, $id_shop);
			}
			// check if subscribed via newsletter module and unsubscribe if true
			$registerStatus = $blocknewsletter->isNewsletterRegistered($emailAddress);
			if ($registerStatus === 1 && $event->Type == 'Deactivate')
				CampaignMonitor::unregisterFromPsNewsletter($emailAddress, $registerStatus, $id_shop);
		}
	}
}
