<?php

namespace App\Exceptions;

use RuntimeException;

/** Tindakan dosen tidak dapat dilakukan pada keadaan saat ini. Pesan ditampilkan apa adanya. */
class TindakanDitolak extends RuntimeException {}
