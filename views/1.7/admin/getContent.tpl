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

{if $errors.apiInfo}
<div class="bootstrap campaignmonitor">
	<div class="alert alert-danger">
		<h4>{l s="There"} {if $errors.apiInfo|sizeof > 1} {l s="are"} {else} {l s="is"} {/if} {$errors.apiInfo|sizeof} {l s="errors"}</h4>
		<ul class="list-unstyled">
			{foreach from=$errors.apiInfo item=error}
			<li>{$error}</li>
			{/foreach}
		</ul>
	</div>
</div>
{/if}

{if $errors.listOptions}
<div class="bootstrap campaignmonitor">
	<div class="alert alert-danger">
		<h4>{l s="There"} {if $errors.apiInfo|sizeof > 1} {l s="are"} {else} {l s="is"} {/if} {$errors.listOptions|sizeof} {l s="error"}</h4>
		<ul class="list-unstyled">
			{foreach from=$errors.listOptions item=error}
			<li>{$error}</li>
			{/foreach}
		</ul>
	</div>
</div>
{/if}

<form id="module_form" action="" method="post" class="campaignMonitorForm defaultForm form-horizontal">
	<div class="panel">

		<div class="panel-heading">{l s="Campaign Monitor API information"}</div>

		<div class="form-wrapper">
			<div class="form-group">
				<label for="cm_client_id" class="control-label col-lg-3">{l s="Campaign Monitor Client ID"}</label>
				<div class="col-lg-9">
					<input id="cm_client_id" type="text" value="{$cmClientID}" name="cm_client_id">
					<p class="help-block">{l s="Example:"} d46c4a3582al33t5yxp0r7ru13zf45z8</p>
				</div>
			</div>

			<div class="form-group">
				<label for="cm_client_api_key" class="control-label col-lg-3">{l s="Campaign Monitor Client API key"}</label>
				<div class="col-lg-9">
					<input id="cm_client_api_key" type="text" value="{$cmClientApiKey}" name="cm_client_api_key">
					<p class="help-block">{l s="Example:"} a43c7aa6c4mp41gnm0n170r4pr351d3n772c5e12jk213me3</p>
				</div>
			</div>

		</div>

		<div class="panel-footer">
			<button id="module_form_submit_btn" class="btn btn-default pull-right" value="{l s='Save Settings'}" name="saveSettings">
				<i class="process-icon-save"></i> {l s='Save Settings'}
			</button>
		</div>

	</div>
</form>

<form id="module_form" action="" method="post" class="campaignMonitorForm defaultForm form-horizontal">
	<div class="panel">

		<div class="panel-heading">{l s="Campaign Monitor List Options"}</div>

		<div class="form-wrapper">

			<div class="form-group">
				<label class="control-label col-lg-3" for="cm_list">{l s="Campaign Monitor List"}</label>
				<div class="col-lg-9">
					<select id="cm_list" name="cm_list" class="form-control fixed-width-xl">
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
					</select>
					<p class="help-block">{l s="The Campaign Monitor destination list"}</p>
				</div>
			</div>


			<div class="form-group">
				<label class="control-label col-lg-3" for="cm_list">{l s="Campaign Monitor custom fields"}</label>
				<div class="col-lg-9">
					<select name="cm_custom_fields[]" size="5" multiple="multiple" class="form-control fixed-width-xl">
						{foreach from=$customfieldsDefault key=value item=field}
							{assign var='selected' value=''}
							{foreach from=$customfields item=objField}
								{if isset($objField->fieldname)}
									{if $value == $objField->fieldname}
										{assign var='selected' value=' selected="selected"'}
									{/if}
								{/if}
							{/foreach}
							<option{$selected} value="{$value}">{l s=$field.fieldname}</option>
						{/foreach}
					</select>
					<p class="help-block">{l s="Custom fields export (press ctrl/cmd to multiselect)"}</p>
				</div>
			</div>

		</div>

		<div class="panel-footer">
			<button id="module_form_submit_btn" class="btn btn-default pull-right" value="{l s='save settings'}" name="saveOptions">
				<i class="process-icon-save"></i> {l s='Save Options'}
			</button>
		</div>

	</div>
</form>

<form action="" method="post" class="defaultForm form-horizontal">
	<div class="panel">
		<div class="panel-heading">{l s="Manual Export"}</div>

		<div class="errors"></div>

		<div class="form-wrapper">
			<div class="form-group">
				<label class="control-label col-lg-4">{l s="Manually export to Campaign Monitor"}</label>
				<div class="col-lg-6">
					<input class="btn btn-default" type="submit" value="{l s='Export Users'}" name="exportToCM" />
				</div>
			</div>

		</div>
	</div>
</form><div class="clear"></div>

<form action="" method="post" class="defaultForm form-horizontal">
	<div class="panel">
		<div class="panel-heading">{l s="Synchronise Data"}</div>

		<div class="errors"></div>

		<div class="form-wrapper">
			<div class="form-group">
				<label class="control-label col-lg-4">
					{l s="Synchronise to Campaign Monitor in case a customer unsubscribed via Prestashop"}
				</label>
				<div class="col-lg-6">
					<input class="btn btn-default" type="submit" value="{l s='Synchronise'}" name="syncData" />
					<div class="clear"></div>
				</div><div class="clear"></div><br />

				<p class="help-block">
					{l s="Prestashop does not provide the possibility to hook into the userprofile update process, so we have to use this workaround until it does."}<br />
					{l s="We have to do this in case a customer un-/subscribes via prestashop so that campaign monitors knows what's going on."}<br /><br />
					{l s="To automate this procedure set up a cronjob with the link below."}<br /><br />
					{$cronUrl}
				</p>

			</div>
		</div>
	</div>
</form><div class="clear"></div>

<form action="" method="post" class="defaultForm form-horizontal">
	<div class="panel">
		<div class="panel-heading">{l s="Campaign Monitor Webhook"}</div>

		<div class="errors"></div>

		<div class="form-wrapper">
			<div class="form-group">
				<label class="control-label">
					{l s="Current domain of the webhook:"} <strong>{$webhookUrl}</strong>
				</label><div class="clear"></div><br />

				<p class="help-block">
					{l s="If the webhook domain does not match your shop domain, you need to tell campaign monitor the correct domain by clicking the button below."}<br />
					{l s="Otherwise the synchronisation from campaign monitor to prestashop will not work."}<br /><br />
					{l s="Full webhook url:"} {$webhookUrlFull}
				</p>
			</div>
		</div>

		<div class="panel-footer">
			<button id="module_form_submit_btn" class="btn btn-default pull-right" value="{l s='save settings'}" name="recreateWebhook">
				<i class="process-icon-refresh"></i> {l s='Update webhook domain'}
			</button>
		</div>
	</div>
</form>
