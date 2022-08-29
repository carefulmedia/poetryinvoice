var gulp = require("gulp");
var less = require('gulp-less');
var sourcemaps = require('gulp-sourcemaps');

gulp.task('less', function () {
    return gulp.src('less/*.less')
    .pipe(sourcemaps.init())
    .pipe(less())
.pipe(sourcemaps.write('./maps'))
        .pipe(gulp.dest("css"));
});

gulp.task('watch', function(){
    gulp.watch("./less/**/*.less", gulp.series('less'));
});

gulp.task('default', gulp.series(['less', 'watch'], function(done) {
    done();
}));

