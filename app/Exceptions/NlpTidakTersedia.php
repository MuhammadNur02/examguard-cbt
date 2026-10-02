<?php

namespace App\Exceptions;

use RuntimeException;

/** Layanan NLP internal tidak dapat dihubungi atau menolak permintaan. */
class NlpTidakTersedia extends RuntimeException {}
