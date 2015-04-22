{*
 * Prestashop Campaign Monitor Sync Module
 *
 * Copyright (C) 2013 - 2015 xport communication GmbH
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
 * @copyright  2013 - 2015 xport communication GmbH
 * @link       http://www.xport.de
 *}

<form action="" method="post" class="campaignMonitorForm">
	<fieldset>
		<legend>{l s="Campaign Monitor API information"}</legend>

		{if $errors.apiInfo}
		<div class="alert error"><ul>
			{foreach from=$errors.apiInfo item=error}
			<li>{$error}</li>
			{/foreach}
		</ul></div>
		{/if}

		<div class="field">
			<label for="cm_client_id">{l s="Campaign Monitor Client ID"}</label>
			<div class="margin-form">
				<input id="cm_client_id" type="text" value="{$cmClientID}" name="cm_client_id">
				<p style="clear:both">
					{l s="Example:"} d46c4a3582al33t5yxp0r7ru13zf45z8
				</p>
			</div>
		</div>

		<div class="field">
			<label for="cm_client_api_key">{l s="Campaign Monitor Client API key"}</label>
			<div class="margin-form">
				<input id="cm_client_api_key" type="text" value="{$cmClientApiKey}" name="cm_client_api_key">
				<div class="clear"></div>
				<p>{l s="Example:"} a43c7aa6c4mp41gnm0n170r4pr351d3n772c5e12jk213me3</p>
			</div>
		</div>

		<div class="submitBtn">
			<input class="button" type="submit" value="{l s='save settings'}" name="saveSettings">
		</div>

	</fieldset>
</form><br /><div class="clear"></div>

<form action="" method="post" class="campaignMonitorForm">
	<fieldset>
		<legend>{l s="Campaign Monitor List Options"}</legend>

		{if $errors.listOptions}
		<div class="alert error"><ul>
			{foreach from=$errors.listOptions item=error}
			<li>{$error}</li>
			{/foreach}
		</ul></div>
		{/if}

		<div class="field">
			<label for="cm_list">{l s="Campaign Monitor List"}</label>
			<div class="margin-form">
				<select id="cm_list" name="cm_list">
					<option></option>
					{if $cmLists != ''}
						{foreach from=$cmLists item=listItem}
							{if $cmSelectedList == $listItem->ListID}
								{assign var='selected' value=' selected="selected"'}
							{else}
								{assign var='selected' value=''}
							{/if}
							<option{$selected} value="{$listItem->ListID}">{$listItem->Name}</option>
						{/foreach}
					{/if}
				</select><div class="clear"></div>
				<p>{l s="The Campaign Monitor destination list"}</p>
			</div>
		</div>

		<div class="field">
			<label for="cm_list">{l s="Campaign Monitor custom fields"}</label>
			<div class="margin-form">
				<select name="cm_custom_fields[]" size="5" multiple="multiple">
					{foreach from=$customfieldsDefault key=value item=field}
						{assign var='selected' value=''}
						{foreach from=$customfields item=objField}
							{if $value == $objField->fieldname}
								{assign var='selected' value=' selected="selected"'}
							{/if}
						{/foreach}
						<option{$selected} value="{$value}">{l s=$field.fieldname}</option>
					{/foreach}
				</select>
				<div class="clear"></div>
				<p>{l s="Custom fields export (press ctrl/cmd to multiselect)"}</p>
			</div>
		</div>

		<div class="submitBtn">
			<input class="button" type="submit" value="{l s='save options'}" name="saveOptions">
		</div>

	</fieldset>
</form><br /><div class="clear"></div>


<form action="" method="post" class="manualExport">
	<fieldset>
		<legend>{l s="Manual Export"}</legend>

		<div class="errors"></div>

		<div>
			<label>{l s="Manually export to Campaign Monitor"}</label>
			<input class="button" type="submit" value="{l s='export users'}" name="exportToCM" />
		</div>
	</fieldset>
</form><br /><div class="clear"></div>

<form action="" method="post" class="syncData">
	<fieldset>
		<legend>{l s="Synchronise Data"}</legend>

		<div class="errors"></div>

		<div>
			<label>
				{l s="Synchronise to Campaign Monitor in case a customer unsubscribed via Prestashop"}
			</label>
			<input class="button" type="submit" value="{l s='synchronise'}" name="syncData" />
			<div class="clear"></div>
			<p class="cronLink">
			{l s="Prestashop does not provide the possibility to hook into the userprofile update process, so we have to use this workaround until it does."}<br />
			{l s="We have to do this in case a customer un-/subscribes via prestashop so that campaign monitors knows what's going on."}<br /><br />
			{l s="To automate this procedure set up a cronjob with the link below."}<br /><br />
			{$cronUrl}
			</p>
		</div>
	</fieldset>
</form>
