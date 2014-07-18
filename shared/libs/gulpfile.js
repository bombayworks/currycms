var gulp = require("gulp");

// include plug-ins
var jshint = require("gulp-jshint");
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var minifyCSS = require('gulp-minify-css');

var files = [
  '../backend/common/js/preconfigure.js',
  // jquery
  'jquery-ui-1.8.17/js/jquery-1.7.1.min.js',
  // jquery-ui
  'jquery-ui-1.8.17/js/jquery-ui-1.8.17.custom.min.js',
  'jquery-ui-1.8.17/css/curry/jquery-ui-1.8.17.custom.css',
  // swfobject
  'swfobject-2.2/swfobject.js',
  // flexigrid
  'flexigrid-1.0b3/flexigrid.js',
  'flexigrid-1.0b3/flexigrid.css',
  // codemirror
  'codemirror-3.02/lib/codemirror.js',
  'codemirror-3.02/mode/xml/xml.js',
  'codemirror-3.02/mode/javascript/javascript.js',
  'codemirror-3.02/mode/css/css.js',
  'codemirror-3.02/lib/codemirror.css',
  // tinymce
  'tinymce-3.5.8-jquery/tiny_mce_src.js',
  'tinymce-3.5.8-jquery/langs/en.js',
  'tinymce-3.5.8-jquery/plugins/{style,table,advimage,advlink,media,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,advlist}/editor_plugin_src.js',
  'tinymce-3.5.8-jquery/plugins/{style,table,advimage,advlink,media,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,advlist}/langs/en.js',
  'tinymce-3.5.8-jquery/themes/advanced/editor_template_src.js',
  'tinymce-3.5.8-jquery/themes/advanced/langs/en.js',
  'tinymce-currypopups/editor_plugin_src.js',
  'tinymce-3.5.8-jquery/jquery.tinymce.js',
  // jquery-bw-url
  '../js/jquery.bw.url.js',
  // dynatree
  'dynatree-1.2.2/jquery/jquery.cookie.js',
  'dynatree-1.2.2/src/jquery.dynatree.js',
  'dynatree-1.2.2/src/skin-vista/ui.dynatree.css',
  'dynatree-1.2.2/src/skin-vista/*.gif',
  // colorpicker
  'colorpicker-20090523/js/colorpicker.js',
  'colorpicker-20090523/css/colorpicker.css',
  // chosen
  'chosen-0.9.12/chosen.jquery.js',
  'chosen-0.9.12/chosen.css',
  // modelview
  '../backend/common/js/modelview.js',
  '../backend/common/css/modelview.css',

  // Curry
  '../css/icons.css',
  '../js/URI.js',

  '../backend/common/js/core.js',
  '../backend/common/js/plugins.js',
  '../backend/common/js/main.js',
  '../backend/common/js/finder.js',
  '../backend/common/js/postconfigure.js'
];

function scripts() {
  return files.filter(function(file) { return /\.js$/.test(file); });
}
function styles() {
  return files.filter(function(file) { return /\.css$/.test(file); });
}
function images() {
  return files.filter(function(file) { return /\.(gif|png)$/.test(file); });
}

// JS concat, strip debugging and minify
gulp.task('scripts', function() {
  gulp.src(scripts())
    .pipe(uglify())
    .pipe(concat('all.min.js'))
    .pipe(gulp.dest('build'));
});

// CSS concat, auto-prefix and minify
gulp.task('styles', function() {
  gulp.src(styles())
    .pipe(concat('all.css'))
    .pipe(minifyCSS())
    .pipe(gulp.dest('build'));
});

gulp.task('images', function() {
  gulp.src(images())
    .pipe(gulp.dest('build'));
});

// Rerun the task when a file changes
gulp.task('watch', function() {
  gulp.watch(scripts(), ['scripts']);
  gulp.watch(styles(), ['styles']);
  gulp.watch(images(), ['images']);
});

gulp.task('default', ['scripts', 'styles', 'images']);
