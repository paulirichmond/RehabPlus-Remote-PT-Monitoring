<?php

namespace App\Services\Firebase;

use RuntimeException;

/** A Firebase call failed or Firebase isn't configured. The message is safe to show staff. */
class FirebaseException extends RuntimeException
{
}
