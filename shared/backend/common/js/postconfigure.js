// TinyMCE hack, need to manually mark compressed files as loaded
tinymce.each([
  'langs/en.js',
  'plugins/advimage/editor_plugin_src.js',
  'plugins/advlink/editor_plugin_src.js',
  'plugins/advlist/editor_plugin_src.js',
  'plugins/contextmenu/editor_plugin_src.js',
  'plugins/fullscreen/editor_plugin_src.js',
  'plugins/media/editor_plugin_src.js',
  'plugins/nonbreaking/editor_plugin_src.js',
  'plugins/paste/editor_plugin_src.js',
  'plugins/style/editor_plugin_src.js',
  'plugins/table/editor_plugin_src.js',
  'plugins/xhtmlxtras/editor_plugin_src.js',
  'themes/advanced/editor_template_src.js',
  'themes/advanced/langs/en.js'
],function(f){tinymce.ScriptLoader.markDone(tinyMCE.baseURL+"/"+f);});

// Workaround for tinymce crashing when sorting sortables
$(document)
  .on('sortstart', '.ui-sortable', function(event) {
    $(this).data('curry-sortable-started', true);
  })
  .on('mouseup.sortable', '.ui-sortable', function(event) {
    if ($(this).data('curry-sortable-started')) {
      var item = $(this).data('sortable').currentItem;
      $(item).find('.tinymce').each(function() {
        var mce = $(this).tinymce();
        $(this).data('curry-sortable-mce', mce.settings);
        mce.remove();
      });
      $(this).data('curry-sortable-started', false);
    }
  })
  .on('sortbeforestop', '.ui-sortable', function(event, ui) {
    $(ui.item).find('.tinymce').each(function() {
      $(this).tinymce($(this).data('curry-sortable-mce'));
      $(this).removeData('curry-sortable-mce');
    });
  });

if(!window.tinymceSettings)
  window.tinymceSettings = {};
window.tinymceSettings = $.extend({
  width: '100%',
  // General options
  theme: 'advanced',
  plugins: 'style,table,advimage,advlink,media,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,advlist,currypopups',
  // Theme options
  theme_advanced_buttons1: 'bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,|,indent,outdent,|,undo,redo,styleselect,formatselect',
  theme_advanced_buttons2: 'link,unlink,anchor,|,image,media,table,hr,charmap,|,blockquote,cite,abbr,acronym,del,ins,sub,sup,|,removeformat,cleanup,code,|,fullscreen,help',
  theme_advanced_resizing : true,
  // Paste from word...
  paste_remove_spans: true,
  paste_remove_styles: true,
  paste_strip_class_attributes: 'all',
  // Example content CSS (should be your site CSS)
  //content_css : 'css/content.css',
  // Drop lists for link/image/media/template dialogs
  external_link_list_url : "", // TODO: Fix this url
  // Replace values for the template plugin
  file_browser_callback: function(fieldName, url, type, win) {
    $.util.openFinder(win.document.getElementById(fieldName));
  }
}, window.tinymceSettings);