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
use PhoNetworksAutogenerated\UserOut\Follow;
use Pho\Lib\Graph\ID;


/**
 * Takes care of Members
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class MembersController extends AbstractController
{
    /**
     * Get Members
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function getMembers(Request $request, Response $response, Kernel $kernel)
    {
        $nodes = $kernel->graph()->members();
        $members = [];
        foreach($nodes as $node) {
            if($node instanceof User) {
                $is_editor = (
                    (isset($node->attributes()->is_editor) && (bool) $node->attributes()->is_editor)
                    ||
                    ($kernel->founder()->id()->equals($node->id()))
                );
                $members[(string) $node->id()] = [
                    "username" => (string) $node->getUsername(),
                    "email" => (string) $node->getEmail(),
                    "avatar" => (string) $node->getAvatar(),
                    "is_editor" => intval($is_editor)
                ];
            }
        }
        $members = $this->paginate($members, $request->getQueryParams(), 20);
        $this->succeed($response, ["members" => $members]);
    }
 
    public function getFollowers(Request $request, Response $response, Session $session, Kernel $kernel)
    {
     $data = $request->getQueryParams();
     if(!isset($data["id"])||!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["id"])) {
       if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->fail($response, "Either session required or a valid ID must be entered.");
        }
     }
        else {
         $id = $data["id"];
        }
        
        $i = $kernel->gs()->node($id);
        $incoming_follows = \iterator_to_array($i->edges()->in(Follow::class));
        $followers = [];
        foreach($incoming_follows as $follow) {
            $follower = $follow->tail();
            $followers[(string) $follower->id()] = array_change_key_case(
                array_filter(
                    $follower->attributes()->toArray(), 
                    function (string $key): bool {
                        return strtolower($key) != "password";
                    },
                    ARRAY_FILTER_USE_KEY
                ), CASE_LOWER
            );
        }
        $this->succeed($response, ["followers"=>$followers]);
    }

    public function getFollowing(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        $data = $request->getQueryParams();
     if(!isset($data["id"])||!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["id"])) {
       if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->fail($response, "Either session required or a valid ID must be entered.");
        }
     }
        else {
         $id = $data["id"];
        }
        $i = $kernel->gs()->node($id);
        $outgoing_follows = \iterator_to_array($i->edges()->out(Follow::class));
        $following = [];
        foreach($outgoing_follows as $follow) {
            $f = $follow->head();
            $following[(string) $f->id()] = array_change_key_case(
                array_filter(
                    $f->attributes()->toArray(), 
                    function (string $key): bool {
                        return strtolower($key) != "password";
                    },
                    ARRAY_FILTER_USE_KEY
                ), CASE_LOWER
            );
        }
        $this->succeed($response, ["following"=>$following]);
    }

    /**
     * Follow someone
     *
     * id
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function follow(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->fail($response, "Session required");
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            $this->fail($response, "Valid user ID required.");
            return;
        }
        if(!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["id"])) {
            $this->fail($response, "Invalid ID");
            return;
        }
        if($data["id"]==$id) {
            $this->fail($response, "Follower and followee can't be the same");
            return;
        }
        $i = $kernel->gs()->node($id);
        $followee = $kernel->gs()->node($data["id"]);
        if(!$i instanceof User) {
            $this->fail($response, "Session owner not a User");
            return;
        }
        if(!$followee instanceof User) {
            $this->fail($response, "Followee not a User");
            return;
        }
        $i->follow($followee);
        $this->succeed($response);
    }

    /**
     * Unfollow someone
     *
     * id
     *
     * @param Request  $request
     * @param Response $response
     * @param Kernel   $kernel
     * 
     * @return void
     */
    public function unfollow(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id = $this->dependOnSession(...\func_get_args()))) {
            return $this->fail($response, "Session required");
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            'id' => 'required',
        ]);
        if($validation->fails()) {
            $this->fail($response, "Valid user ID required.");
            return;
        }
        if(!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["id"])) {
            $this->fail($response, "Invalid ID");
            return;
        }
        $i = $kernel->gs()->node($id);
        $followee = $kernel->gs()->node($data["id"]);
        if(!$i instanceof User) {
            $this->fail($response, "Session owner not a User");
            return;
        }
        if(!$followee instanceof User) {
            $this->fail($response, "Followee not a User");
            return;
        }
        $follow_edges = $i->edges()->to($followee->id(), Follow::class);
        if(count($follow_edges)!=1) {
            $this->fail($response, "No follow edge found");
            return;
        }
        //eval(\Psy\sh());
        $follow_edges->current()->destroy();
        $this->succeed($response);
    }

}
