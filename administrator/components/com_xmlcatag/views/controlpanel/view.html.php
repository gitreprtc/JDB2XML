<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class XmlcatagViewControlpanel extends HtmlView
{
    public function display($tpl = null)
    {

        if (Factory::getApplication()->getInput()->getCmd('task') === 'preview') {
            $this->preview = $this->get('Preview');
        }

        
        // C2: mixed-state visualisation (client-side only)
        $doc = Factory::getDocument();
        $doc->addStyleDeclaration('
            .xmlcatag-node.mixed{background:#fff6e5;border-left:4px solid #de9a3a;padding-left:6px;}
            .xmlcatag-node.mixed::after{content:"gemengd";font-size:11px;color:#de9a3a;margin-left:8px;}
        ');
        $doc->addScriptDeclaration('
            document.addEventListener("DOMContentLoaded", function () {
                function updateMixedStates(){
                    document.querySelectorAll(".xmlcatag-tree li").forEach(function(li){
                        var children = li.querySelectorAll(":scope > ul > li");
                        if(!children.length) return;
                        var excluded = 0;
                        children.forEach(function(child){
                            var cb = child.querySelector("input[type=\"checkbox\"]");
                            if(cb && cb.checked) excluded++;
                        });
                        var node = li.querySelector(":scope > .xmlcatag-node");
                        if(!node) return;
                        node.classList.remove("mixed");
                        if(excluded > 0 && excluded < children.length){
                            node.classList.add("mixed");
                        }
                    });
                }
                document.addEventListener("change", function(e){
                    if(e.target && e.target.matches(".xmlcatag-tree input[type=\"checkbox\"]")){
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
        
                if (t.classList.contains("xmlcatag-exclude-all") || t.classList.contains("xmlcatag-include-all")) {
                    e.preventDefault();
                    var li = t.closest("li");
                    if (!li) return;
                    var exclude = t.classList.contains("xmlcatag-exclude-all");
                    li.querySelectorAll("input[type=\"checkbox\"]").forEach(function(cb){
                        cb.checked = exclude ? true : false;
                        cb.dispatchEvent(new Event("change", {bubbles:true}));
                    });
                }
            });
        ');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('XML Cat/Tag', 'stack');

        // These buttons submit the adminForm with the given task.
        ToolbarHelper::custom('preview', 'search', 'search', 'Preview', false);
        ToolbarHelper::custom('import', 'upload', 'upload', 'Import', false);
        ToolbarHelper::custom('export', 'download', 'download', 'Export', false);

        ToolbarHelper::divider();
        ToolbarHelper::custom('rollback', 'undo', 'undo', 'Rollback', false);
    }
}
