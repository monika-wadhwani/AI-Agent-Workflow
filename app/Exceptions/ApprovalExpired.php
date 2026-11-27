<?php

namespace App\Exceptions;

use LogicException;

/** Someone tried to decide an approval after its deadline. */
class ApprovalExpired extends LogicException {}
