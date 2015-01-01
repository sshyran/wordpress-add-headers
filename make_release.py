
# Copyright George Notaras

REL_FILES = [
	'add-headers.php',
    'index.php',
    'AUTHORS',
	'LICENSE',
#	'README',
    'readme.txt',
    'uninstall.php',
]

REL_DIRS = [
#    'languages',
]

PLUGIN_METADATA_FILE = 'add-headers.php'

POT_HEADER = """#  POT (Portable Object Template)
#
#  This file is part of the Add-Headers plugin for WordPress.
#
#  Copyright (C) 2013 George Notaras <gnot@g-loaded.eu>
#
"""

# ==============================================================================

import sys
import os
import glob
import zipfile
import shutil
import subprocess
import polib

def get_name_release():
    def get_data(cur_line):
        return cur_line.split(':')[1].strip()
    f = open(PLUGIN_METADATA_FILE)
    name = ''
    release = ''
    for line in f:
        if line.lower().startswith('plugin name:'):
            name = get_data(line)
        elif line.lower().startswith('version:'):
            release = get_data(line)
        if name and release:
            break
    f.close()
    
    if not name:
        raise Exception('Cannot determine plugin name')
    elif not release:
        raise Exception('Cannot determine plugin version')
    else:
        # Replace spaces in name and convert it to lowercase
        name = name.replace(' ', '-')
        name = name.lower()
        return name, release

name, release = get_name_release()


print 'Creating distribution package...'
# Create release dir and move release files inside it
os.mkdir(name)
# Copy files
for p_file in REL_FILES:
	shutil.copy(p_file, os.path.join(name, p_file))
# Copy dirs
for p_dir in REL_DIRS:
    shutil.copytree(p_dir, os.path.join(name, p_dir))

# Create distribution package

d_package_path = '%s-%s.zip' % (name, release)
d_package = zipfile.ZipFile(d_package_path, 'w', zipfile.ZIP_DEFLATED)

# Append root files
for p_file in REL_FILES:
	d_package.write(os.path.join(name, p_file))
# Append language directory
for p_dir in REL_DIRS:
    d_package.write(os.path.join(name, p_dir))
    # Append files in that directory
    for p_file in os.listdir(os.path.join(name, p_dir)):
        d_package.write(os.path.join(name, p_dir, p_file))

d_package.testzip()

d_package.comment = 'Official packaging by CodeTRAX'

d_package.printdir()

d_package.close()


# Remove the release dir

shutil.rmtree(name)

print 'Complete'
print
