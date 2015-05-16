// Default TinyMCE settings
window.tinymceSettings = $.extend({
  width: '100%',
  theme: 'modern',
  document_base_url: document.baseURI,
  plugins: 'table,image,autolink,link,media,contextmenu,paste,searchreplace,lists,advlist,hr,anchor,visualblocks,visualchars,code,fullscreen',
  skin_url: '/shared/libs/build/tinymce-skin-lightgray',
  file_browser_callback: function(fieldName, url, type, win) {
    $.util.openFinder(win.document.getElementById(fieldName));
  }
}, window.tinymceSettings);

// Workaround for tinymce crashing when sorting jquery-ui sortables
$(document)
  .on('sortstart', '.ui-sortable', function(event) {
    $(this).data('curry-sortable-started', true);
  })
  .on('mouseup.sortable', '.ui-sortable', function(event) {
    if ($(this).data('curry-sortable-started')) {
      var item = $(this).data('ui-sortable').currentItem;
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