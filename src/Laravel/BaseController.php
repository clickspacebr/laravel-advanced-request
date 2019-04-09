<?php

namespace Clickspace\AdvancedRequest\Laravel;

use Clickspace\AdvancedRequest\ControllerTrait;
use Clickspace\AdvancedRequest\EloquentBuilder;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class  BaseController extends Controller {

    use EloquentBuilder, AuthorizesRequests, DispatchesJobs, ValidatesRequests, ControllerTrait;

}
