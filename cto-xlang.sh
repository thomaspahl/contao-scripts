#!/bin/bash
#######################################################################
#   Contao: remove superfluous languages
# USAGE
#   1. Edit the lines defining the languages and countries you want to
#      keep
#   2. Run this script in the root folder of the Contao installation
#   3. Rebuild the Contao internal cache (BE "System maintenance")
# DESCRIPTION
#   This script reduces the languages and countries both in drop-down
#   lists and in the system cache (for languages) to a defined list
#   of desired languages/countries.
#   Shorter drop-down lists showing only the relevant choices improve the
#   UX, less languages in core speed up system cache creation.
#
#   Languages are only removed in system/modules/core/languages/, not
#   in any extension, as the language cache will only be built for
#   languages present in core (see Automator.php).
#
#   En detail, the script does the following:
#   - in system/modules/core/languages/ all language subfolders
#     which are not in the list of desired languages are moved to a new
#     folder system/modules/core/languages.off/
#     By this, they are out of sight for rebuildung the internal cache and
#     for some other places in Contao handling languages.
#   - in system/config/countries.php and system/config/languages.php all
#     countries/languages are deactived (by converting the line to a
#     comment) so that they are no longer displayed in the respective
#     option lists (drop-downs).
#
#   Alternatively to editing the languages= and countries= lines below
#   you may also add the following lines to localconfig.php (outside of
#   the part marked INSTALL SCRIPT):
#   $keep_languages = 'de en da fr nl';
#   $keep_countries = 'de gb dk fr nl us';
#   (choose your sets).
#   Such a specification in localconfig.php takes precedence.
#
# NOTES
#   After running this script, Contao check will be unhappy.
#   After updating Contao, run this script again.
#   No data is deleted permanently, with a little manual work everything
#   can be restored.
#
# BUGS
#   If you run it a second time with more languages, these new languages
#   will be restored. But there is no option to restore all languages
#   and countries.
# AUTHOR
#   Thomas Pahl 2013, github.com/thomaspahl/
#######################################################################

#
# define the languages and countries you want to keep here
#
languages="de en da fr nl"
countries="de gb dk fr nl us"

#
# file configuration - for Contao 3.x
#
languages_dir=system/modules/core/languages
config=system/config/localconfig.php
countries_file=system/config/countries.php
languages_file=system/config/languages.php

tmp=${TMP:=/tmp}/ctolang$$
tmpcnt=$tmp.cnt
tmplng=$tmp.lng

#
# start
#
if [ ! -d $languages_dir ]
then
	echo "Called in wrong directory - must be in root of an installation."
	exit 1
fi

#
# see if languages/countries list defined in localconfig.php
#
eval $( sed -n -e '
/^\$keep_/ {
	s!^[[:space:]]*\$!!
	s!,! !g
	s![[:space:]]*=[[:space:]]*!=!
	s!;.*!!
	p
}
' <$config )

# if we got specs, use these
if [ -n "$keep_languages" ]
then
	languages="$keep_languages"
fi
if [ -n "$keep_countries" ]
then
	countries="$keep_countries"
fi

# prepare sed script to disable countries in countries.php
echo '/=>/ {' >$tmpcnt
echo "s!^!//!" >>$tmpcnt
for i in $countries
do
	echo "/'$i'/s!^//!!" >>$tmpcnt
done
echo '}' >>$tmpcnt

# prepare sed script to disable languages in languages.php
echo '/=>/ {' >$tmplng
echo "s!^!//!" >>$tmplng
for i in $languages
do
	echo "/'$i'/s!^//!!" >>$tmplng
done
echo '}' >>$tmplng

#
# remove language files
#
mkdir -p $languages_dir.off
# restore all languages
mv $languages_dir.off/* $languages_dir 2>/dev/null

(
cd $languages_dir || exit 99
off=../$(basename $languages_dir.off)
for i in *
do
	for ikeep in $languages
	do
		if [ "$i" = "$ikeep" ]
		then
			continue 2
		fi
	done
	mv $i $off
done
ls
)

#
# config countries
#
if [ ! -f $countries_file.orig ]
then
	cp -p $countries_file $countries_file.orig
fi
sed -f $tmpcnt <$countries_file.orig >$countries_file

#
# config languages
#
if [ ! -f $languages_file.orig ]
then
	cp -p $languages_file  $languages_file.orig
fi
sed -f $tmplng <$languages_file.orig >$languages_file

rm -f $tmp.*
