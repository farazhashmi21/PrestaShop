<?php
/*
* 2007-2012 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2012 PrestaShop SA
*  @version  Release: $Revision: 7095 $
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require dirname(__FILE__).'/menutoplinks.class.php';

class Blocktopmenu extends Module
{
	private $_menu = '';
	private $_html = '';

    /*
     * Pattern for matching config values
     */
    private $pattern = '/^([A-Z_]*)[0-9]+/';

    /*
     * Name of the controller
     * Used to set item selected or not in top menu
     */
    private $page_name = '';

    /*
     * Spaces per depth in BO
     */
    private $spacer_size = '5';

	public function __construct()
	{
		$this->name = 'blocktopmenu';
		$this->tab = 'front_office_features';
		$this->version = 1.4;
		$this->author = 'PrestaShop';

		parent::__construct();

		$this->displayName = $this->l('Top horizontal menu');
		$this->description = $this->l('Add a new menu on top of your shop.');
	}

	public function install()
	{
		if (!parent::install() ||
			!$this->registerHook('top') ||
			!Configuration::updateGlobalValue('MOD_BLOCKTOPMENU_ITEMS', 'CAT1,CMS1,CMS2,PRD1') ||
			!Configuration::updateGlobalValue('MOD_BLOCKTOPMENU_SEARCH', '1') ||
			!$this->installDB())
			return false;
		return true;
	}

	public function installDb()
	{
		return (Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'linksmenutop` (
			`id_linksmenutop` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`id_shop` INT UNSIGNED NOT NULL,
			`new_window` TINYINT( 1 ) NOT NULL,
			`link` VARCHAR( 128 ) NOT NULL,
			INDEX (`id_shop`)
		) ENGINE = '._MYSQL_ENGINE_.' CHARACTER SET utf8 COLLATE utf8_general_ci;') &&
		Db::getInstance()->execute('
			 CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'linksmenutop_lang` (
			`id_linksmenutop` INT NOT NULL,
			`id_lang` INT NOT NULL,
			`id_shop` INT NOT NULL,
			`label` VARCHAR( 128 ) NOT NULL ,
			INDEX ( `id_linksmenutop` , `id_lang`, `id_shop`)
		) ENGINE = '._MYSQL_ENGINE_.' CHARACTER SET utf8 COLLATE utf8_general_ci;'));
	}

	public function uninstall()
	{
		if (!parent::uninstall() ||
			!Configuration::deleteByName('MOD_BLOCKTOPMENU_ITEMS') ||
			!Configuration::deleteByName('MOD_BLOCKTOPMENU_SEARCH') ||
			!$this->uninstallDB())
			return false;
		return true;
	}

	private function uninstallDb()
	{
		Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'linksmenutop`');
		Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'linksmenutop_lang`');
		return true;
	}

	public function getContent()
	{
        $id_lang = (int)Context::getContext()->language->id;
        $languages = $this->context->controller->getLanguages();
        $default_language = Configuration::get('PS_LANG_DEFAULT');

        $labels = Tools::getValue('label') ? array_filter(Tools::getValue('label'), 'strlen') : array();
        $spacer = str_repeat('&nbsp;', $this->spacer_size);
        $divLangName = 'link_label';

		if (Tools::isSubmit('submitBlocktopmenu'))
		{
			if (Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', Tools::getValue('items')))
				$this->_html .= $this->displayConfirmation($this->l('Settings Updated'));
			else
				$this->_html .= $this->displayError($this->l('Unable to update settings'));
			Configuration::updateValue('MOD_BLOCKTOPMENU_SEARCH', (bool)Tools::getValue('search'));
		}
		else if (Tools::isSubmit('submitBlocktopmenuLinks'))
		{

            if ((Tools::getValue('link') == '') && (!count($labels)))
                ;
            else if (Tools::getValue('link') == '')
                $this->_html .= $this->displayError($this->l('Please, fill the "Link" field'));
            else if (!count($labels))
                $this->_html .= $this->displayError($this->l('Please add a label'));
            else if (!isset($labels[$default_language]))
                $this->_html .= $this->displayError($this->l('Please add a label for your default language'));
			else
			{
				MenuTopLinks::add(Tools::getValue('link'), Tools::getValue('label'), Tools::getValue('new_window', 0), (int)Shop::getContextShopID());
				$this->_html .= $this->displayConfirmation($this->l('The link has been added'));
			}
		}
		else if (Tools::isSubmit('submitBlocktopmenuRemove'))
		{
			$id_linksmenutop = Tools::getValue('id_linksmenutop', 0);
			MenuTopLinks::remove($id_linksmenutop, (int)Shop::getContextShopID());
			Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', str_replace(array('LNK'.$id_linksmenutop.',', 'LNK'.$id_linksmenutop), '', Configuration::get('MOD_BLOCKTOPMENU_ITEMS')));
			$this->_html .= $this->displayConfirmation($this->l('The link has been removed'));
		}
		
		$this->_html .= '
		<fieldset>
			<div class="multishop_info">
			'.$this->l('The modifications will be applied to').' '.(Shop::getContext() == Shop::CONTEXT_SHOP ? $this->l('shop:').' '.$this->context->shop->name : $this->l('all shops')).'.
			</div>
			<legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Settings').'</legend>
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" id="form">
				<div style="display: none">
				<label>'.$this->l('Items').'</label>
				<div class="margin-form">
					<input type="text" name="items" id="itemsInput" value="'.Configuration::get('MOD_BLOCKTOPMENU_ITEMS').'" size="70" />
				</div>
				</div>

				<div class="clear">&nbsp;</div>
				<table style="margin-left: 130px;">
					<tbody>
						<tr>
							<td>
								<select multiple="multiple" id="items" style="width: 300px; height: 160px;">';
								$this->makeMenuOption();
								$this->_html .= '</select><br/>
								<br/>
								<a href="#" id="removeItem" style="border: 1px solid rgb(170, 170, 170); margin: 2px; padding: 2px; text-align: center; display: block; text-decoration: none; background-color: rgb(250, 250, 250); color: rgb(18, 52, 86);">'.$this->l('Remove').' &gt;&gt;</a>
							</td>
							<td style="padding-left: 20px;">
								<select multiple="multiple" id="availableItems" style="width: 300px; height: 160px;">';

								// BEGIN CMS
								$this->_html .= '<optgroup label="'.$this->l('CMS').'">';
                                $this->getCMSOptions(0, 1, $id_lang);
								$this->_html .= '</optgroup>';

								// BEGIN SUPPLIER
								$this->_html .= '<optgroup label="'.$this->l('Supplier').'">';
								$suppliers = Supplier::getSuppliers(false, $id_lang);
								foreach ($suppliers as $supplier)
									$this->_html .= '<option value="SUP'.$supplier['id_supplier'].'">'.$spacer.$supplier['name'].'</option>';
								$this->_html .= '</optgroup>';

								// BEGIN Manufacturer
								$this->_html .= '<optgroup label="'.$this->l('Manufacturer').'">';
								$manufacturers = Manufacturer::getManufacturers(false, $id_lang);
								foreach ($manufacturers as $manufacturer)
									$this->_html .= '<option value="MAN'.$manufacturer['id_manufacturer'].'">'.$spacer.$manufacturer['name'].'</option>';
								$this->_html .= '</optgroup>';

								// BEGIN Categories
								$this->_html .= '<optgroup label="'.$this->l('Categories').'">';
								$this->getCategoryOption(1, $id_lang);
								$this->_html .= '</optgroup>';

								// BEGIN Products
								$this->_html .= '<optgroup label="'.$this->l('Products').'">';
									$this->_html .= '<option value="PRODUCT" style="font-style:italic">'.$spacer.$this->l('Choose ID product').'</option>';
								$this->_html .= '</optgroup>';

								// BEGIN Menu Top Links
								$this->_html .= '<optgroup label="'.$this->l('Menu Top Links').'">';
								$links = MenuTopLinks::gets($id_lang, null, (int)Shop::getContextShopID());
								foreach ($links as $link)
                                {
                                    if ($link['label'] == '')
                                    {
                                        $link = MenuTopLinks::get($link['id_linksmenutop'], $default_language, (int)Shop::getContextShopID());
                                        $this->_html .= '<option value="LNK'.$link[0]['id_linksmenutop'].'">'.$spacer.$link[0]['label'].'</option>';
                                    }
                                    else
                                        $this->_html .= '<option value="LNK'.$link['id_linksmenutop'].'">'.$spacer.$link['label'].'</option>';
                                }
								$this->_html .= '</optgroup>';

								$this->_html .= '</select><br />
								<br />
								<a href="#" id="addItem" style="border: 1px solid rgb(170, 170, 170); margin: 2px; padding: 2px; text-align: center; display: block; text-decoration: none; background-color: rgb(250, 250, 250); color: rgb(18, 52, 86);">&lt;&lt; '.$this->l('Add').'</a>
							</td>
						</tr>
					</tbody>
				</table>
				<div class="clear">&nbsp;</div>
				<script type="text/javascript">
				$(document).ready(function(){
					$("#addItem").click(add);
					$("#availableItems").dblclick(add);
					$("#removeItem").click(remove);
					$("#items").dblclick(remove);
					function add()
					{
						$("#availableItems option:selected").each(function(i){
							var val = $(this).val();
							var text = $(this).text();
	                        text = text.replace(/(^\s*)|(\s*$)/gi,"");
							if (val == "PRODUCT")
							{
								val = prompt("'.$this->l('Set ID product').'");
								if (val == null || val == "" || isNaN(val))
									return;
								text = "'.$this->l('Product ID').' "+val;
								val = "PRD"+val;
							}
							$("#items").append("<option value=\""+val+"\">"+text+"</option>");
						});
						serialize();
						return false;
					}
					function remove()
					{
						$("#items option:selected").each(function(i){
							$(this).remove();
						});
						serialize();
						return false;
					}
					function serialize()
					{
						var options = "";
						$("#items option").each(function(i){
							options += $(this).val()+",";
						});
						$("#itemsInput").val(options.substr(0, options.length - 1));
					}
				});
				</script>
				<label for="s">'.$this->l('Search Bar').'</label>
				<div class="margin-form">
					<input type="checkbox" name="search" id="s" value="1"'.((Configuration::get('MOD_BLOCKTOPMENU_SEARCH')) ? ' checked=""': '').'/>
				</div>
				<p class="center">
					<input type="submit" name="submitBlocktopmenu" value="'.$this->l('	Save	').'" class="button" />
				</p>
			</form>
		</fieldset><br />';

		$this->_html .= '
		<fieldset>
			<legend><img src="../img/admin/add.gif" alt="" title="" />'.$this->l('Add Menu Top Link').'</legend>
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" id="form">
				<label>'.$this->l('Label').'</label>
				<div class="margin-form">';
				foreach ($languages as $language)
				{
					$this->_html .= '
					<div id="link_label_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $id_lang ? 'block' : 'none').';float: left;">
						<input type="text" name="label['.$language['id_lang'].']" id="label_'.$language['id_lang'].'" size="70" value="'.(isset($labels[$language['id_lang']]) ? $labels[$language['id_lang']] : '').'" />
					</div>';
				 }
				$this->_html .= $this->displayFlags($languages, $id_lang, $divLangName, 'link_label', true);

				$this->_html .= '</div><p class="clear"> </p>
				<label>'.$this->l('Link').'</label>
				<div class="margin-form">
					<input type="text" name="link" value="'.Tools::getValue('link').'" size="70" />
				</div>
				<label>'.$this->l('New Window').'</label>
				<div class="margin-form">
					<input type="checkbox" name="new_window" value="1" '.(Tools::getValue('new_window') ? 'checked' : '').'/>
				</div>
				<p class="center">
					<input type="submit" name="submitBlocktopmenuLinks" value="'.$this->l('	Add	').'" class="button" />
				</p>
			</form>
		</fieldset><br />';

        $links = MenuTopLinks::gets($id_lang, null, Shop::getContextShopID());

        if (!count($links))
            return $this->_html;

		$this->_html .= '
		<fieldset>
			<legend><img src="../img/admin/details.gif" alt="" title="" />'.$this->l('List Menu Top Link').'</legend>
			<table style="width:100%;">
				<thead>
					<tr>
						<th>'.$this->l('Id Link').'</th>
						<th>'.$this->l('Label').'</th>
						<th>'.$this->l('Link').'</th>
						<th>'.$this->l('New Window').'</th>
						<th>'.$this->l('Action').'</th>
					</tr>
				</thead>
				<tbody>';
				foreach ($links as $link)
				{
					$this->_html .= '
					<tr>
						<td>'.$link['id_linksmenutop'].'</td>
						<td>'.$link['label'].'</td>
						<td><a href="'.$link['link'].'"'.(($link['new_window']) ? ' target="_blank"' : '').'>'.$link['link'].'</a></td>
						<td>'.(($link['new_window']) ? $this->l('Yes') : $this->l('No')).'</td>
						<td>
							<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
								<input type="hidden" name="id_linksmenutop" value="'.$link['id_linksmenutop'].'" />
								<input type="submit" name="submitBlocktopmenuRemove" value="'.$this->l('Remove').'" class="button" />
							</form>
						</td>
					</tr>';
				}
				$this->_html .= '</tbody>
			</table>
		</fieldset>';
		return $this->_html;
	}

	private function getMenuItems()
	{
		return explode(',', Configuration::get('MOD_BLOCKTOPMENU_ITEMS'));
	}

	private function makeMenuOption()
	{
        $menu_item = $this->getMenuItems();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)Shop::getContextShopID();

		foreach ($menu_item as $type => $item)
        {
            if (!$item)
                continue;

            preg_match($this->pattern, $item, $values);
            $id = (int)substr($item, strlen($values[1]), strlen($item));

            switch (substr($item, 0, strlen($values[1])))
            {
                case 'CAT':
                    $category = new Category($id, $id_lang);
                    if (!is_null($category->id))
                        $this->_html .= '<option value="CAT'.$id.'">'.$category->name.'</option>'.PHP_EOL;
                    break;

                case 'PRD':
                    $product = new Product($id, true, $id_lang);
                    if (!is_null($product->id))
                        $this->_html .= '<option value="PRD'.$id.'">'.$product->name.'</option>'.PHP_EOL;
                    break;

                case 'CMS':
                    $cms = new CMS($id, $id_lang);
                    if (count($cms))
                        $this->_html .= '<option value="CMS'.$id.'">'.$cms->meta_title.'</option>'.PHP_EOL;
                    break;

                case 'CMS_CAT':
                    $category = new CMSCategory($id, $id_lang);
                    if (count($category))
                        $this->_html .= '<option value="CMS_CAT'.$id.'">'.$category->name.'</option>'.PHP_EOL;
                    break;

                case 'MAN':
                    $manufacturer = new Manufacturer($id, $id_lang);
                    if (!is_null($manufacturer->id))
                        $this->_html .= '<option value="MAN'.$id.'">'.$manufacturer->name.'</option>'.PHP_EOL;
                    break;

                case 'SUP':
                    $supplier = new Supplier($id, $id_lang);
                    if (!is_null($supplier->id))
                        $this->_html .= '<option value="SUP'.$id.'">'.$supplier->name.'</option>'.PHP_EOL;
                    break;

                case 'LNK':
                    $link = MenuTopLinks::get($id, $id_lang, $id_shop);
                    if (count($link))
                    {
                        if (!isset($link[0]['label']) || ($link[0]['label'] == ''))
                        {
                            $default_language = Configuration::get('PS_LANG_DEFAULT');
                            $link = MenuTopLinks::get($link[0]['id_linksmenutop'], $default_language, (int)Shop::getContextShopID());
                        }
                        $this->_html .= '<option value="LNK'.$link[0]['id_linksmenutop'].'">'.$link[0]['label'].'</option>';
                    }
                    break;
            }
        }
	}

	private function makeMenu()
	{
        $this->page_name = Dispatcher::getInstance()->getController();

        $menu_items = $this->getMenuItems();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)Shop::getContextShopID();

		foreach ($menu_items as $type => $item)
		{
            if (!$item)
                continue;

            preg_match($this->pattern, $item, $value);
            $id = (int)substr($item, strlen($value[1]), strlen($item));

            switch (substr($item, 0, strlen($value[1])))
            {
                case 'CAT':
                    $this->getCategory($id);
                    break;

                case 'PRD':
                    $selected = ($this->page_name == 'product' && (Tools::getValue('id_product') == $id)) ? ' class="sfHover"' : '';
                    $product = new Product($id, true, $id_lang);
                    if (!is_null($product->id))
                        $this->_menu .= '<li'.$selected.'><a href="'.$product->getLink().'">'.$product->name.'</a></li>'.PHP_EOL;
                    break;

                case 'CMS':
                    $selected = ($this->page_name == 'cms' && (Tools::getValue('id_cms') == $id)) ? ' class="sfHover"' : '';
                    $cms = CMS::getLinks($id_lang, array($id));
                    if (count($cms))
                        $this->_menu .= '<li'.$selected.'><a href="'.$cms[0]['link'].'">'.$cms[0]['meta_title'].'</a></li>'.PHP_EOL;
                    break;

                case 'CMS_CAT':
                    $category = new CMSCategory($id, $id_lang);
                    if (count($category))
                    {
                        $this->_menu .= '<li><a href="#">'.$category->name.'</a>';
                        $this->getCMSMenuItems($category->id);
                        $this->_menu .= '</li>'.PHP_EOL;
                    }
                    break;

                case 'MAN':
                    $selected = ($this->page_name == 'manufacturer' && (Tools::getValue('id_manufacturer') == $id)) ? ' class="sfHover"' : '';
                    $manufacturer = new Manufacturer($id, $id_lang);
                    if (!is_null($manufacturer->id))
                    {
                        if (intval(Configuration::get('PS_REWRITING_SETTINGS')))
                            $manufacturer->link_rewrite = Tools::link_rewrite($manufacturer->name, false);
                        else
                            $manufacturer->link_rewrite = 0;
                        $link = new Link;
                        $this->_menu .= '<li'.$selected.'><a href="'.$link->getManufacturerLink($id, $manufacturer->link_rewrite).'">'.$manufacturer->name.'</a></li>'.PHP_EOL;
                    }
                    break;

                case 'SUP':
                    $selected = ($this->page_name == 'supplier' && (Tools::getValue('id_supplier') == $id)) ? ' class="sfHover"' : '';
                    $supplier = new Supplier($id, $id_lang);
                    if (!is_null($supplier->id))
                    {
                        $link = new Link;
                        $this->_menu .= '<li'.$selected.'><a href="'.$link->getSupplierLink($id, $supplier->link_rewrite).'">'.$supplier->name.'</a></li>'.PHP_EOL;
                    }
                    break;

                case 'LNK':
                    $link = MenuTopLinks::get($id, $id_lang, $id_shop);
                    if (count($link))
                    {
                        if (!isset($link[0]['label']) || ($link[0]['label'] == ''))
                        {
                            $default_language = Configuration::get('PS_LANG_DEFAULT');
                            $link = MenuTopLinks::get($link[0]['id_linksmenutop'], $default_language, (int)Shop::getContextShopID());
                        }
                        $this->_menu .= '<li><a href="'.$link[0]['link'].'"'.(($link[0]['new_window']) ? ' target="_blank"': '').'>'.$link[0]['label'].'</a></li>'.PHP_EOL;
                    }
                    break;
            }
		}
	}

    private function getCategoryOption($id_category = 1, $id_lang = false, $recursive = true)
    {
        $id_lang = (int)($id_lang ? $id_lang : Shop::getContextShopID());
        $category = new Category($id_category, $id_lang);

        if (is_null($category->id))
            return;

        if ($recursive)
        {
            $childrens = Category::getChildren((int)$id_category, $id_lang);
            $spacer = str_repeat('&nbsp;', $this->spacer_size * (int)$category->level_depth);
        }

        $this->_html .= '<option value="CAT'.(int)$category->id.'">'.(isset($spacer) ? $spacer : '').$category->name.'</option>';

        if (isset($childrens) && count($childrens))
            foreach ($childrens as $children)
                $this->getCategoryOption((int)$children['id_category'], $id_lang);
    }

	private function getCategory($id_category, $id_lang = false)
	{
        $id_lang = $id_lang ? $id_lang : Shop::getContextShopID();
        $category = new Category($id_category, $id_lang);

        if (is_null($category->id))
			return;

        $childrens = Category::getChildren($id_category, $id_lang);
        $selected = ($this->page_name == 'category' && ((int)Tools::getValue('id_category') == $id_category)) ? ' class="sfHoverForce"' : '';
		$this->_menu .= '<li '.$selected.'>';
        $this->_menu .= '<a href="'.$category->getLink().'">'.$category->name.'</a>';

        if (count($childrens))
		{
			$this->_menu .= '<ul>';

			foreach ($childrens as $children)
				$this->getCategory($children['id_category'], $id_lang);

			$this->_menu .= '</ul>';
		}
		$this->_menu .= '</li>';
	}

    private function getCMSMenuItems($parent, $depth = 1, $id_lang = false)
    {
        $id_lang = $id_lang ? $id_lang : (int)Context::getContext()->language->id;

        if ($depth > 3)
            return;

        $categories = $this->getCMSCategories(false, $parent, $id_lang);
        $pages = $this->getCMSPages($parent);

        if (count($categories) || count($pages))
        {
            $this->_menu .= '<ul>';

            foreach ($categories as $category)
            {
                $this->_menu .= '<li>';
                $this->_menu .= '<a href="#">'.$category['name'].'</a>';
                $this->getCMSMenuItems($category['id_cms_category'], $depth + 1);
                $this->_menu .= '</li>';
            }

            foreach ($pages as $page)
            {
                $cms = new CMS($page['id_cms'], $id_lang);
                $links = $cms->getLinks($id_lang, array($cms->id));

                $selected = ($this->page_name == 'cms' && ((int)Tools::getValue('id_cms') == $page['id_cms'])) ? ' class="sfHoverForce"' : '';
                $this->_menu .= '<li '.$selected.'>';
                $this->_menu .= '<a href="'.$links[0]['link'].'">'.$cms->meta_title.'</a>';
                $this->_menu .= '</li>';
            }

            $this->_menu .= '</ul>';
        }
    }

    private function getCMSOptions($parent = 0, $depth = 1, $id_lang = false)
    {
        $id_lang = $id_lang ? $id_lang : (int)Context::getContext()->language->id;

        $categories = $this->getCMSCategories(false, $parent, $id_lang);
        $pages = $this->getCMSPages($parent, false, $id_lang);

        $spacer = str_repeat('&nbsp;', $this->spacer_size * (int)$depth);

        foreach ($categories as $category)
        {
            $this->_html .= '<option value="CMS_CAT'.$category['id_cms_category'].'" style="font-weight: bold;">'.$spacer.$category['name'].'</option>';
            $this->getCMSOptions($category['id_cms_category'], $depth + 1, $id_lang);
        }

        foreach ($pages as $page)
            $this->_html .= '<option value="CMS'.$page['id_cms'].'">'.$spacer.$page['meta_title'].'</option>';
    }

	public function hookTop($param)
	{
		global $smarty;

		$this->makeMenu();

		$smarty->assign('MENU_SEARCH', Configuration::get('MOD_BLOCKTOPMENU_SEARCH'));
		$smarty->assign('MENU', $this->_menu);
		$smarty->assign('this_path', $this->_path);

		$this->context->controller->addJS($this->_path.'js/hoverIntent.js');
		$this->context->controller->addJS($this->_path.'js/superfish-modified.js');
		$this->context->controller->addCSS($this->_path.'css/superfish-modified.css');

		return $this->display(__FILE__, 'blocktopmenu.tpl');
	}

    private function getCMSCategories($recursive = false, $parent = 1, $id_lang = false)
    {
        $id_lang = $id_lang ? $id_lang : (int)Context::getContext()->language->id;

        if ($recursive === false)
        {
            $sql = 'SELECT bcp.`id_cms_category`, bcp.`id_parent`, bcp.`level_depth`, bcp.`active`, bcp.`position`, cl.`name`, cl.`link_rewrite`
				FROM `'._DB_PREFIX_.'cms_category` bcp
				INNER JOIN `'._DB_PREFIX_.'cms_category_lang` cl
				ON (bcp.`id_cms_category` = cl.`id_cms_category`)
				WHERE cl.`id_lang` = '.$id_lang.'
				AND bcp.`id_parent` = '.(int)$parent;

            return Db::getInstance()->executeS($sql);
        }
        else
        {
            $sql = 'SELECT bcp.`id_cms_category`, bcp.`id_parent`, bcp.`level_depth`, bcp.`active`, bcp.`position`, cl.`name`, cl.`link_rewrite`
				FROM `'._DB_PREFIX_.'cms_category` bcp
				INNER JOIN `'._DB_PREFIX_.'cms_category_lang` cl
				ON (bcp.`id_cms_category` = cl.`id_cms_category`)
				WHERE cl.`id_lang` = '.$id_lang.'
				AND bcp.`id_parent` = '.(int)$parent;

            $results = Db::getInstance()->executeS($sql);
            foreach ($results as $result)
            {
                $sub_categories = $this->getCMSCategories(true, $result['id_cms_category'], $id_lang);
                if ($sub_categories && count($sub_categories) > 0)
                    $result['sub_categories'] = $sub_categories;
                $categories[] = $result;
            }

            return isset($categories) ? $categories : false;
        }

    }

    private function getCMSPages($id_cms_category, $id_shop = false, $id_lang = false)
    {
        $id_shop = ($id_shop !== false) ? $id_shop : (int)Context::getContext()->shop->id;
        $id_lang = $id_lang ? $id_lang : (int)Context::getContext()->language->id;

        $sql = 'SELECT c.`id_cms`, cl.`meta_title`, cl.`link_rewrite`
			FROM `'._DB_PREFIX_.'cms` c
			INNER JOIN `'._DB_PREFIX_.'cms_shop` cs
			ON (c.`id_cms` = cs.`id_cms`)
			INNER JOIN `'._DB_PREFIX_.'cms_lang` cl
			ON (c.`id_cms` = cl.`id_cms`)
			WHERE c.`id_cms_category` = '.(int)$id_cms_category.'
			AND cs.`id_shop` = '.$id_shop.'
			AND cl.`id_lang` = '.$id_lang.'
			AND c.`active` = 1
			ORDER BY `position`';

        return Db::getInstance()->executeS($sql);
    }
}