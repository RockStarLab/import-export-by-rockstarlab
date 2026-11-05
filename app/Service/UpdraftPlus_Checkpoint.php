<?php
/**
 * Internal control flow for a completed native archive.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Raised only after UpdraftPlus saves its durable archive checkpoint. */
class UpdraftPlus_Checkpoint extends \RuntimeException {}
