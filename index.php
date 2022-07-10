<?php

/**
 * @defgroup plugins_importexport_sample
 */

/**
 * @file plugins/importexport/cdlImport/index.php
 *
 * Copyright (c) 2022 Cast Iron Coding
 * Distributed under the BSD 3 Clause license. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_sample
 * @brief OJS plugin for importing data, ARKs and license URLs to start
 *
 */

// $Id$

require_once('Classes/CdlImportPlugin.php');

/**
 * @psalm-suppress MissingDependency
 */
return new CdlImportPlugin();
