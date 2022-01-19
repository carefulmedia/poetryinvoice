var gulp = require("gulp");
var less = require('gulp-less');

gulp.task('less', function () {
    return gulp.src('assets/less/*.less')
        .pipe(less())
        .pipe(gulp.dest("assets/css"));
});

gulp.task('watch', function(){
    gulp.watch("./assets/less/**/*.less", gulp.series('less'));
});

gulp.task('default', gulp.series(['less', 'watch'], function(done) {
    done();
}));

