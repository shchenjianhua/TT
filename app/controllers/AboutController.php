<?php

namespace TTDemo\Controllers;

class AboutController extends ControllerBase
{
    public function initialize()
    {
        $this->tag->setTitle('About us');

        parent::initialize();
    }

    public function indexAction()
    {
    }
}
