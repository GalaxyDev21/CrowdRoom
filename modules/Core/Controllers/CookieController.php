<?php
	/**
	 * Created by PhpStorm.
	 * User: h2 gaming
	 * Date: 8/13/2019
	 * Time: 10:19 PM
	 */

	namespace Modules\Core\Controllers;

	use App\Http\Controllers\Controller;
	use Illuminate\Http\Request;

	class CookieController extends Controller
	{
		public function saveCookie(Request $request)
		{
			$name = 'booking_cookie_agreement_enable';
			echo $request->cookie($name);
			if (!isset($_COOKIE[$name])) {
				return $this->sendSuccess([], 'done')->cookie($name,1);
			} else {
				return $this->sendError(__('You cant save cookie'));
			}
		}
	}