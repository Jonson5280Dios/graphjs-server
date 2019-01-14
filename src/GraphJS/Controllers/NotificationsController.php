<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphJS\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;
use Pho\Lib\Graph\ID;


 /**
 * Takes care of Notifications
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class NotificationsController extends AbstractController
{
    public function read(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return;
        }
        $i = $kernel->gs()->node($id);
        if(!$i instanceof User) {
            $this->fail($response, "Session Problem");
            return;
        }
        $ret = [];
        $c=0;
        $notifications = $i->notifications()->read(5);
        error_log(print_r($notifications->toArray(), true));
        error_log("started");
        foreach($notifications as $notification) {
            error_log(print_r($notification->toArray(), true));
            $ret[$c]["username"] =  $notification->edge()->tail()->getUsername();
            $ret[$c]["avatar"] =  $notification->edge()->tail()->getAvatar();
            $ret[$c]["label"] =  $notification->label();
            $c+=1;
        }
        $this->succeed($response, $ret);
    }
}