<?php

namespace Clickspace\AdvancedRequest\Lumen;

use Clickspace\AdvancedRequest\ControllerTrait;
use Clickspace\AdvancedRequest\EloquentBuilder;
use Laravel\Lumen\Routing\Controller;

abstract class BaseController extends Controller
{

    use EloquentBuilder, ControllerTrait;

}
