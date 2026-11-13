@echo off
REM ------------------------------------------------------------
REM  EduSphere - run all PHPUnit tests (double-click this file)
REM  Needs: XAMPP MySQL running.
REM  The first time it downloads phpunit.phar (one file, needs internet).
REM  Output is also saved to tests-output.txt
REM ------------------------------------------------------------
cd /d "%~dp0"
set "LOG=tests-output.txt"
echo EduSphere tests - %date% %time% > "%LOG%"

set "PHP=php"
where php >nul 2>nul || set "PHP=C:\xampp\php\php.exe"
"%PHP%" -v >> "%LOG%" 2>&1

if exist phpunit.phar goto run
echo Downloading PHPUnit (first time only)...
echo --- downloading phpunit.phar --- >> "%LOG%"
"%PHP%" -r "copy('https://phar.phpunit.de/phpunit-11.phar', 'phpunit.phar') or exit(1);" >> "%LOG%" 2>&1

:run
echo --- phpunit --- >> "%LOG%"
"%PHP%" phpunit.phar --testdox --colors=never >> "%LOG%" 2>&1
chcp 65001 >nul
type "%LOG%"
echo.
pause
