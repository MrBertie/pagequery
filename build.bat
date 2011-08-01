@ECHO OFF

set name=pagequery
set source=C:\Webserver\www\wiki\lib\plugins\pagequery
set target=C:\Dev\pagequery\pagequery
set excludes=*FOSSIL* manifest* nbproject build* pagequery *diff changes* *build*

echo.
echo.
echo ****************************************************************************
echo Building %name% Plugin on %DATE% at %TIME%
echo ****************************************************************************
echo.
echo.

if "%1"=="" (
    echo No version number provided, using current date instead.
    echo.
    echo.
    set version=%DATE%
) else (
    set version=%1
    rem update the plugin.info.txt file version no.
    echo Updating version no. in plugin.info.txt file...
    sfk filt plugin.info.txt -ls+version -rep _?.*.?_%version%_
)


rem need to do a check for de&&bug messages---remove!
echo Removing any overlooked debug lines...
echo.
sfk list -dir . -file .php +filefilter -!de??bug -write -yes


echo ============================================================================
echo Copying the source files...
echo ============================================================================
robocopy %source% %target% /E /MIR /XF %excludes% /FP /NJS /TEE

if not errorlevel 0 goto copyfailure

echo.
echo.
echo =============================================================================
echo Creating the new zip release: version %version%
echo =============================================================================
7za a %target%_%version%.zip %target%\

if not errorlevel 0 goto zipfailure
echo.
echo.
echo =============================================================================
echo New release was created: %target%_%version%.zip
echo =============================================================================
goto end

:copyfailure
echo ###########################################
echo Not able to copy the source code correctly
echo Check the paths and try again.
goto end

:zipfailure
echo ########################################################
echo Problems with zipping the folder (name, version, etc...)
echo Maybe check and try again.

:end