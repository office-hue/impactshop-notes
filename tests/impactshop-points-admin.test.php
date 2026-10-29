<?php
/** Verify the real REST handler's authentication and event-bound zero branch. */
define('ABSPATH',__DIR__.'/');
function add_action(...$args) {}
function add_filter(...$args) {}
$allowed=true;$calls=[];
function current_user_can($cap) { return $GLOBALS['allowed']; }
function get_current_user_id() { return 7; }
class WP_REST_Request { public function __construct(private array $body){} public function get_json_params(){return $this->body;} }
class WP_REST_Response { public function __construct(public $data,public $status){} }
class Sharity_Impactshop_Points_Manager { public function resolve_impactshop_event(...$args){$GLOBALS['calls'][]=$args;return ['success'=>true,'actual_delta'=>0];} }
require __DIR__.'/../wp-content/mu-plugins/sharity-points-api.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
$body=['review_event_id'=>42,'expected_version'=>3,'points'=>0,'reason'=>'Not eligible','pseudo_id'=>'attacker1234'];
$r=sharity_points_admin_adjust(new WP_REST_Request($body));
check($r->status===200 && $calls===[[42,3,0,'Not eligible',7]],'zero event-bound resolution before generic zero rejection; no caller-supplied pseudo');
$allowed=false;$calls=[];
check(sharity_points_admin_adjust(new WP_REST_Request($body))->status===403 && !$calls,'role denied before manager');
$allowed=true;$body['points']='0';
check(sharity_points_admin_adjust(new WP_REST_Request($body))->status===400 && !$calls,'string numeric coercion rejected');
echo "Impact Shop admin boundary passed\n";
