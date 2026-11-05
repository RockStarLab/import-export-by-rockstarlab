<?php
/**
 * Compatibility name for the shared job lock introduced with import services.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Import and export now share the same per-job synchronization primitive.
 */
class Import_Job_Lock extends Job_Lock {}
