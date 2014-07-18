var gulp = require("gulp");

// include plug-ins
var jshint = require("gulp-jshint");
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var minifyCSS = require('gulp-minify-css');

var files = [
  '../backend/common/js/preconfigure.js',
  // jquery
  'bower_components/jquery/dist/jquery.js',
  // jquery-ui
  'bower_components/jquery-ui/ui/{core,widget,mouse,position,draggable,droppable,resizable,selectable,sortable}.js',
  // flexigrid
  'flexigrid-1.0b3/flexigrid.js',
  'flexigrid-1.0b3/flexigrid.css',
  // codemirror
  'bower_components/codemirror/lib/codemirror.js',
  'bower_components/codemirror/lib/codemirror.css',
  'bower_components/codemirror/mode/xml/xml.js',
  'bower_components/codemirror/mode/javascript/javascript.js',
  'bower_components/codemirror/mode/css/css.js',
  // tinymce
  'tinymce-3.5.8-jquery/tiny_mce_src.js',
  'tinymce-3.5.8-jquery/langs/en.js',
  'tinymce-3.5.8-jquery/plugins/{style,table,advimage,advlink,media,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,advlist}/editor_plugin_src.js',
  'tinymce-3.5.8-jquery/plugins/{style,table,advimage,advlink,media,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,advlist}/langs/en.js',
  'tinymce-3.5.8-jquery/themes/advanced/editor_template_src.js',
  'tinymce-3.5.8-jquery/themes/advanced/langs/en.js',
  'tinymce-currypopups/editor_plugin_src.js',
  'tinymce-3.5.8-jquery/jquery.tinymce.js',
  // dynatree
  'bower_components/dynatree/jquery/jquery.cookie.js',
  'bower_components/dynatree/dist/jquery.dynatree.js',
  'bower_components/dynatree/dist/skin-vista/ui.dynatree.css',
  'bower_components/dynatree/dist/skin-vista/*.gif',
  // chosen
  'bower_components/chosen/chosen.jquery.js',
  'bower_components/chosen/chosen.css',
  // URI.js
  'bower_components/uri.js/src/URI.js',
  // modelview
  '../backend/common/js/modelview.js',
  '../backend/common/css/modelview.css',
  // Curry
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
