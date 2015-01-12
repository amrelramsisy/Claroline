<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Claroline\CursusBundle;

use Claroline\CoreBundle\Library\PluginBundle;

class ClarolineCursusBundle extends PluginBundle
{
    public function hasMigrations()
    {
        return false;
    }
}
