{**
 * @file templates/pixelTagsAll.tpl
 *
 * Author: Božana Bokan, Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Last update: September 25, 2015
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display list of pixel tags for editor management.
 *
 *}
{assign var="pageTitle" value="plugins.generic.vgWort.pixelTagsAll"}

{include file="../plugins/generic/vgWort/templates/pixelTagsHeader.tpl"}
<br />
{include file="../plugins/generic/vgWort/templates/pixelTagSearch.tpl"}
<br />
<br />

{assign var=colspan value="4"}
{assign var=colspanPage value="2"}
<div id="pixelTags">
<table width="100%" class="listing">
	<tr>
		<td colspan="{$colspan}" class="headseparator">&nbsp;</td>
	</tr>
	<tr class="heading" valign="bottom">
		<td width="25%">{translate key="plugins.generic.vgWort.pixelTag.privateCode"}</td>
		<td width="25%">{translate key="plugins.generic.vgWort.pixelTag.publicCode"}</td>
		<td width="25%">{translate key="plugins.generic.vgWort.pixelTag.date_ordered"}</td>
		<td width="25%">{translate key="common.status"}</td>
	</tr>
	<tr>
		<td colspan="{$colspan}" class="headseparator">&nbsp;</td>
	</tr>

{iterate from=pixelTags item=pixelTag}
	<tr valign="top">
		<td>{$pixelTag->getPrivateCode()|escape}</td>
		<td>{$pixelTag->getPublicCode()|escape}</td>
		<td>{$pixelTag->getDateOrdered()|date_format:$dateFormatShort}</td>
		<td>{$pixelTag->getStatusString()|escape}</td>
	</tr>
	<tr>
		<td colspan="{$colspan}" class="{if $pixelTags->eof()}end{/if}separator">&nbsp;</td>
	</tr>
{/iterate}
{if $pixelTags->wasEmpty()}
	<tr>
		<td colspan="{$colspan}" class="nodata">{translate key="plugins.generic.vgWort.none"}</td>
	</tr>
	<tr>
		<td colspan="{$colspan}" class="endseparator">&nbsp;</td>
	</tr>
{else}
	<tr>
		<td colspan="{$colspanPage}" align="left">{page_info iterator=$pixelTags}</td>
		<td colspan="{$colspanPage}" align="right">{page_links anchor="pixelTags" name="pixelTags" iterator=$pixelTags}</td>
	</tr>
{/if}
</table>
</div>

{include file="common/footer.tpl"}
