#!/bin/sh
# Baut dist/ neu (Wrapper für build-dist.php)
cd "$(dirname "$0")" && php build-dist.php
