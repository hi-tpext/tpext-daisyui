<?php

namespace tpext\builder\table;

class TEmpty
{
    public function __call($name, $arguments)
    {
        return $this;
    }
}
