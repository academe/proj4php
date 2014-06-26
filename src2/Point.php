<?php

class Point
{
    protected $mgrs;

    public function __construct()
    {
        $this->mgrs = new Mgrs();
    }

    public function fromMGRS($mgrsStr)
    {
        return new Point($this->mgrs->toPoint($mgrsStr));
    }
}

