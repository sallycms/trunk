@echo off
call uglifyjs -nc --unsafe -o ../sally/backend/assets/js/standard.min.js ../sally/backend/assets/js/standard.js
call uglifyjs -nc --unsafe -o ../sally/backend/assets/js/jquery.datetime.min.js ../sally/backend/assets/js/jquery.datetime.js
call uglifyjs -nc --unsafe -o ../sally/backend/assets/js/locales/de_de.min.js ../sally/backend/assets/js/locales/de_de.js
call uglifyjs -nc --unsafe -o ../sally/backend/assets/js/locales/en_gb.min.js ../sally/backend/assets/js/locales/en_gb.js
call uglifyjs -nc --unsafe -o ../sally/backend/assets/js/jquery.imgcheckbox.min.js ../sally/backend/assets/js/jquery.imgcheckbox.js
