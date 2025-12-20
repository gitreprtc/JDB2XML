<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class Jdb2xmlViewControlpanel extends HtmlView
{
    public function display($tpl = null)
    {

        if (Factory::getApplication()->getInput()->getCmd('task') === 'preview') {
            $this->preview = $this->get('Preview');
        }

        
        // C2: mixed-state visualisation (client-side only)
        $doc = Factory::getDocument();
        $doc->addStyleDeclaration('
            .jdb2xml-node.mixed{background:transparent;border-left:0;padding-left:0;}
            .jdb2xml-node.mixed::after{content:"mixed";font-size:inherit;color:inherit;margin-left:8px;}
            #toolbar-preview .btn{background:#2e7d32;border-color:#2e7d32;color:#fff;}
            #toolbar-import .btn{background:#2e7d32;border-color:#2e7d32;color:#fff;}
            #toolbar-resetpreview .btn{background:#f39c12;border-color:#f39c12;color:#fff;}
            #toolbar-refreshsftp .btn{background:#f39c12;border-color:#f39c12;color:#fff;}
            #toolbar-home{margin-left:auto;}
            #toolbar-home .btn{background:#2e7d32;border-color:#2e7d32;color:#fff;}
            #toolbar-rejectimport .btn{background:#c0392b;border-color:#c0392b;color:#fff;}
        ');
        $doc->addScriptDeclaration('
            document.addEventListener("DOMContentLoaded", function () {
                function updateMixedStates(){
                    var rows = Array.from(document.querySelectorAll(".jdb2xml-row[data-depth]"));
                    rows.forEach(function(row, index){
                        var depth = parseInt(row.getAttribute("data-depth") || "0", 10);
                        var childRows = [];
                        for (var i = index + 1; i < rows.length; i++) {
                            var nextDepth = parseInt(rows[i].getAttribute("data-depth") || "0", 10);
                            if (nextDepth <= depth) break;
                            if (nextDepth === depth + 1) {
                                childRows.push(rows[i]);
                            }
                        }
                        if (!childRows.length) return;
                        var excluded = 0;
                        childRows.forEach(function(child){
                            var cb = child.querySelector("input[type=\"checkbox\"]");
                            if(cb && cb.checked) excluded++;
                        });
                        var node = row.querySelector(".jdb2xml-node");
                        if(!node) return;
                        node.classList.remove("mixed");
                        if(excluded > 0 && excluded < childRows.length){
                            node.classList.add("mixed");
                        }
                    });
                }
                document.addEventListener("change", function(e){
                    if(e.target && e.target.matches(".jdb2xml-table input[type=\"checkbox\"]")){
                        updateMixedStates();
                    }
                });
                updateMixedStates();
            });
        ');

        // E2: subtree include/exclude (event delegation, survives redraw)
        $doc->addScriptDeclaration('
            document.addEventListener("click", function(e){
                var t = e.target;
                if (!t || !t.classList) return;
        
                if (t.classList.contains("jdb2xml-exclude-all") || t.classList.contains("jdb2xml-include-all")) {
                    e.preventDefault();
                    var row = t.closest("tr");
                    if (!row) return;
                    var rows = Array.from(document.querySelectorAll(".jdb2xml-row[data-depth]"));
                    var index = rows.indexOf(row);
                    if (index === -1) return;
                    var depth = parseInt(row.getAttribute("data-depth") || "0", 10);
                    var exclude = t.classList.contains("jdb2xml-exclude-all");
                    row.querySelectorAll("input[type=\"checkbox\"]").forEach(function(cb){
                        cb.checked = exclude ? true : false;
                        cb.dispatchEvent(new Event("change", {bubbles:true}));
                    });
                    for (var i = index + 1; i < rows.length; i++) {
                        var nextDepth = parseInt(rows[i].getAttribute("data-depth") || "0", 10);
                        if (nextDepth <= depth) break;
                        rows[i].querySelectorAll("input[type=\"checkbox\"]").forEach(function(cb){
                            cb.checked = exclude ? true : false;
                            cb.dispatchEvent(new Event("change", {bubbles:true}));
                        });
                    }
                }
            });
        ');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('JDB2XML', 'stack');

        // These buttons submit the adminForm with the given task.
        ToolbarHelper::custom('preview', 'search', 'search', 'Preview', false);
        ToolbarHelper::custom('resetpreview', 'refresh', 'refresh', 'Reset preview', false);
        ToolbarHelper::custom('import', 'upload', 'upload', 'Import', false);
        ToolbarHelper::custom('rejectimport', 'cancel', 'cancel', 'Reject', false);
        ToolbarHelper::custom('refreshsftp', 'refresh', 'refresh', 'Refresh SFTP', false);
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=landing', 'Main menu', 'home');
    }
}
