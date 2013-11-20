<?php
/**
 * Curry CMS
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://currycms.com/license
 *
 * @category   Curry CMS
 * @package    Curry
 * @copyright  2011-2012 Bombayworks AB (http://bombayworks.se)
 * @license    http://currycms.com/license GPL
 * @link       http://currycms.com
 */

/**
 * Class used to store templates for InlineAdmin.
 * 
 * @package Curry
 *
 */
class Curry_InlineAdmin
{
	/**
	 * Flag indicating whether InlineAdmin is enabled or not.
	 *
	 * @var bool
	 */
	public static $active = false;
	
	public static function getAdminPanelTpl(){
		return <<<TPL
<div id="curry-admin-panel">
	<h2>Administrate</h2>
	<ul>
		{% for command in commands %}
		<li><a href="{{command.Url}}" class="{{command.Class}}">{{command.Name}}</a></li>
		{% endfor %}
	</ul>
</div>
TPL;
	}


	public static function getAdminBlockTpl(){
		return <<<TPL
<div id="block-{{Id}}-open" class="block-open" style="display: none">
	<div class="block-control">
		<h2>{{Target}}</h2>
		<ul>
			{% for command in commands %}
			<li><a href="{{command.Url}}" class="{{command.Class}}">{{command.Name}}</a></li>
			{% endfor %}
		</ul>
	</div>
</div>
{{Content|raw}}
<div id="block-{{Id}}-close" class="block-close"></div>
TPL;
	}


	public static function getAdminModuleTpl(){
		return <<<TPL
<div id="module-{{Id}}-open" class="module-open" style="display: none" data-module="{{ {id: Id, page_id: curry.page.PageId}|json_encode }}">
	<div class="module-control">
		<h2 title="{{ClassName}}"><span class="close">&times;</span> {{Name}}</h2>
		<ul class="commands">
			{% for command in commands %}
			<li><a href="{{command.Url}}" class="{{command.Class}}">{{command.Name}}</a></li>
			{% endfor %}
		</ul>
	</div>
</div>
{{Content|raw}}
<div id="module-{{Id}}-close" class="module-close"></div>
TPL;
	}

	public static function getAdminItemStartTpl(){
		return <<<TPL
<div id="module-{{Id}}-open" class="module-open" style="display: none">
	<div class="module-control">
		<h2 title="{{ClassName}}">{{Name}}</h2>
		<ul class="commands">
			<li><a href="{{Url}}" class="iframe">Edit</a></li>
		</ul>
	</div>
</div>
TPL;
	}

	public static function getAdminItemEndTpl(){
		return <<<TPL
<div id="module-{{Id}}-close" class="module-close"></div>
TPL;
	}
	
}