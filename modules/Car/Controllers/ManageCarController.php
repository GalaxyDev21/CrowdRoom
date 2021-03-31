<?php
namespace Modules\Car\Controllers;

use Modules\Car\Models\Car;
use Modules\Car\Models\CarTerm;
use Modules\Car\Models\CarTranslation;
use Modules\FrontendController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Location\Models\Location;
use Modules\Core\Models\Attributes;
use Modules\Booking\Models\Booking;

class ManageCarController extends FrontendController
{
    protected $carClass;
    protected $carTranslationClass;
    protected $carTermClass;
    protected $attributesClass;
    protected $locationClass;
    protected $bookingClass;
    public function __construct()
    {
        parent::__construct();
        $this->carClass = Car::class;
        $this->carTranslationClass = CarTranslation::class;
        $this->carTermClass = CarTerm::class;
        $this->attributesClass = Attributes::class;
        $this->locationClass = Location::class;
        $this->bookingClass = Booking::class;
    }

    public function callAction($method, $parameters)
    {
        if(!Car::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }
    public function manageCar(Request $request)
    {
        $this->checkPermission('car_view');
        $user_id = Auth::id();
        $list_tour = $this->carClass::where("create_user", $user_id)->orderBy('id', 'desc');
        $data = [
            'rows' => $list_tour->paginate(5),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Cars'),
                    'url'  => route('car.vendor.index')
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Manage Cars"),
        ];
        return view('Car::frontend.manageCar.index', $data);
    }

    public function createCar(Request $request)
    {
        $this->checkPermission('car_create');
        $row = new $this->carClass();
        $data = [
            'row'           => $row,
            'translation' => new $this->carTranslationClass(),
            'car_location' => $this->locationClass::where("status","publish")->get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'car')->get(),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Cars'),
                    'url'  => route('car.vendor.index')
                ],
                [
                    'name'  => __('Create'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Create Cars"),
        ];
        return view('Car::frontend.manageCar.detail', $data);
    }


    public function store( Request $request, $id ){
        if($id>0){
            $this->checkPermission('car_update');
            $row = $this->carClass::find($id);
            if (empty($row)) {
                return redirect(route('car.vendor.index'));
            }

            if($row->create_user != Auth::id() and !$this->hasPermission('car_manage_others'))
            {
                return redirect(route('car.vendor.index'));
            }
        }else{
            $this->checkPermission('car_create');
            $row = new $this->carClass();
            $row->status = "publish";
            if(setting_item("car_vendor_create_service_must_approved_by_admin", 0)){
                $row->status = "pending";
            }
        }
        $dataKeys = [
            'title',
            'content',
            'price',
            'is_instant',
            'video',
            'faqs',
            'image_id',
            'banner_image_id',
            'gallery',
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',
            'number',
            'price',
            'sale_price',
            'passenger',
            'gear',
            'baggage',
            'door',
            'enable_extra_price',
            'extra_price',
            'is_featured',
            'default_state',
        ];
        if($this->hasPermission('car_manage_others')){
            $dataKeys[] = 'create_user';
        }

        $row->fillByAttr($dataKeys,$request->input());

        $res = $row->saveOriginOrTranslation($request->input('lang'),true);

        if ($res) {
            if(!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
            }

            if($id > 0 ){
                return back()->with('success',  __('Car updated') );
            }else{
                return redirect(route('car.vendor.edit',['id'=>$row->id]))->with('success', __('Car created') );
            }
        }
    }

    public function saveTerms($row, $request)
    {
        if (empty($request->input('terms'))) {
            $this->carTermClass::where('target_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->carTermClass::firstOrCreate([
                    'term_id' => $term_id,
                    'target_id' => $row->id
                ]);
            }
            $this->carTermClass::where('target_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
        }
    }

    public function editCar(Request $request, $id)
    {
        $this->checkPermission('car_update');
        $user_id = Auth::id();
        $row = $this->carClass::where("create_user", $user_id);
        $row = $row->find($id);
        if (empty($row)) {
            return redirect(route('car.vendor.index'))->with('warning', __('Car not found!'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        $data = [
            'translation'    => $translation,
            'row'           => $row,
            'car_location' => $this->locationClass::where("status","publish")->get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'car')->get(),
            "selected_terms" => $row->terms->pluck('term_id'),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Cars'),
                    'url'  => route('car.vendor.index')
                ],
                [
                    'name'  => __('Edit'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Edit Cars"),
        ];
        return view('Car::frontend.manageCar.detail', $data);
    }

    public function deleteCar($id)
    {
        $this->checkPermission('car_delete');
        $user_id = Auth::id();
        $query = $this->carClass::where("create_user", $user_id)->where("id", $id)->first();
        if(!empty($query)){
            $query->delete();
        }
        return redirect(route('car.vendor.index'))->with('success', __('Delete car success!'));
    }

    public function bulkEditCar($id , Request $request){
        $this->checkPermission('car_update');
        $action = $request->input('action');
        $user_id = Auth::id();
        $query = $this->carClass::where("create_user", $user_id)->where("id", $id)->first();
        if (empty($id)) {
            return redirect()->back()->with('error', __('No item!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }
        if(empty($query)){
            return redirect()->back()->with('error', __('Not Found'));
        }
        switch ($action){
            case "make-hide":
                $query->status = "draft";
                break;
            case "make-publish":
                $query->status = "publish";
                break;
        }
        $query->save();
        return redirect()->back()->with('success', __('Update success!'));
    }

    public function bookingReport(Request $request)
    {
        $data = [
            'bookings' => $this->bookingClass::getBookingHistory($request->input('status'), false , Auth::id() , 'car'),
            'statues'  => config('booking.statuses'),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Car'),
                    'url'  => route('car.vendor.index')
                ],
                [
                    'name' => __('Booking Report'),
                    'class'  => 'active'
                ]
            ],
            'page_title'         => __("Booking Report"),
        ];
        return view('Car::frontend.manageCar.bookingReport', $data);
    }

    public function bookingReportBulkEdit($booking_id , Request $request){
        $status = $request->input('status');
        if (!empty(setting_item("car_allow_vendor_can_change_their_booking_status")) and !empty($status) and !empty($booking_id)) {
            $query = $this->bookingClass::where("id", $booking_id);
            $query->where("vendor_id", Auth::id());
            $item = $query->first();
            if(!empty($item)){
                $item->status = $status;
                $item->save();
                $item->sendStatusUpdatedEmails();
                return redirect()->back()->with('success', __('Update success'));
            }
            return redirect()->back()->with('error', __('Booking not found!'));
        }
        return redirect()->back()->with('error', __('Update fail!'));
    }
}