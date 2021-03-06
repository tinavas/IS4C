<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

use COREPOS\pos\lib\gui\BasicCorePage;
use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\ReceiptLib;

session_cache_limiter('nocache');

include_once(dirname(__FILE__).'/../lib/AutoLoader.php');

class pos2 extends BasicCorePage 
{
    private $display;

    function preprocess()
    {
        $this->display = "";

        $ajax = new COREPOS\pos\ajax\AjaxParser();
        $ajax->enablePageDrawing(false);
        $json = $ajax->ajax(array('field'=>'reginput'));
        $redirect = $this->doRedirect($json);
        if ($redirect !== false) {
            $this->change_page($redirect);
            return false;
        }
        $this->setOutput($json);
        $this->registerRetry($json);
        $this->registerPrintJob($json);
        if (CoreLocal::get('CustomerDisplay') == true) {
            $this->loadCustomerDisplay();
        }

        return true;
    }

    private function doRedirect($json)
    {
        if (isset($json['main_frame']) && $json['main_frame'] != false) {
            return $json['main_frame'];
        } else {
            return false;
        }
    }

    private function setOutput($json)
    {
        if (isset($json['output']) && !empty($json['output'])) {
            $this->display = $json['output'];
        }
    }

    private function registerRetry($json)
    {
        if (isset($json['retry']) && $json['retry'] != false) {
            $this->add_onload_command("setTimeout(\"pos2.inputRetry('".$json['retry']."');\", 150);\n");
        }
    }

    private function registerPrintJob($json)
    {
        if (isset($json['receipt']) && $json['receipt'] != false) {
            $ref = isset($json['trans_num']) ? $json['trans_num'] : ReceiptLib::mostRecentReceipt();
            $this->add_onload_command("pos2.receiptFetch('" . $json['receipt'] . "', '" . $ref . "');\n");
        }
    }

    private function loadCustomerDisplay()
    {
        if (CoreLocal::get('CustomerDisplay') == true) {
            $child_url = MiscLib::baseURL() . 'gui-modules/posCustDisplay.php';
            $this->add_onload_command("setCustomerURL('{$child_url}');\n");
            $this->add_onload_command("reloadCustomerDisplay();\n");
        }
    }

    function head_content()
    {
        ?>
        <script type="text/javascript" src="<?php echo $this->page_url; ?>js/ajax-parser.js"></script>
        <script type="text/javascript" src="<?php echo $this->page_url; ?>js/CustomerDisplay.js"></script>
        <Script type="text/javascript" src="js/pos2.js"></script>
        <script type="text/javascript">
        function parseWrapper(str){
            $('#reginput').val(str);
            pos2.submitWrapper();
        }
        </script>
        <?php
    }

    function body_content()
    {
        $lines = DisplayLib::screenLines();
        $this->input_header('action="pos2.php" onsubmit="return pos2.submitWrapper();"');
        if (CoreLocal::get("timeout") != "") {
            $timeout = sprintf('%d', CoreLocal::get('timeout'));
            $this->add_onload_command("pos2.enableScreenLock({$timeout});\n");
        }
        $this->add_onload_command("pos2.setNumLines({$lines});\n");
        $this->add_onload_command("\$('#reginput').keydown(pos2.keydown);\n");

        echo '<div class="baseHeight">';

        CoreLocal::set("quantity",0);
        CoreLocal::set("multiple",0);
        CoreLocal::set("casediscount",0);
        // set memberID if not set already
        if (!CoreLocal::get("memberID")) {
            CoreLocal::set("memberID","0");
        }

        if (CoreLocal::get("plainmsg") && strlen(CoreLocal::get("plainmsg")) > 0) {
            echo DisplayLib::printheaderb();
            echo "<div class=\"centerOffset\">";
            echo DisplayLib::plainmsg(CoreLocal::get("plainmsg"));
            CoreLocal::set("plainmsg",0);
            echo "</div>";
        } elseif (!empty($this->display)) {
            echo $this->display;
        } else {
            echo DisplayLib::lastpage();
        }

        echo "</div>"; // end base height

        echo "<div id=\"footer\">";
        echo DisplayLib::printfooter();
        echo "</div>";

        if (CoreLocal::get("touchscreen")) {
            $this->touchScreenKeys();
        }
    } // END body_content() FUNCTION

    private function touchScreenKeys()
    {
        echo '<div style="text-align: center;">
        <button type="submit" 
            class="quick_button pos-button coloredBorder"
            style="margin: 0 10px 0 0;"
            onclick="parseWrapper(\'QO1001\');">
            Items
        </button>
        <button type="submit"
            class="quick_button pos-button coloredBorder"
            style="margin: 0 10px 0 0;"
            onclick="parseWrapper(\'QO1002\');">
            Total
        </button>
        <button type="submit" 
            class="quick_button pos-button coloredBorder"
            style="margin: 0 10px 0 0;"
            onclick="parseWrapper(\'QO1003\');">
            Member
        </button>
        <button type="submit" 
            class="quick_button pos-button coloredBorder"
            style="margin: 0 10px 0 0;"
            onclick="parseWrapper(\'QO1004\');">
            Tender
        </button>
        <button type="submit"
            class="quick_button pos-button coloredBorder"
            style="margin: 0 10px 0 0;"
            onclick="parseWrapper(\'QO1005\');">
            Misc
        </button>
        </div>';
    }
}

AutoLoader::dispatch();

