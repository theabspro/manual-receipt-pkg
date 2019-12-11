<?php

namespace Abs\ReceiptPkg;
use Abs\ReceiptPkg\Receipt;
use App\Address;
use App\Country;
use App\Http\Controllers\Controller;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Validator;
use Yajra\Datatables\Datatables;

class ReceiptController extends Controller {

	public function __construct() {
	}

	public function getReceiptList(Request $request) {
		$receipt_list = Receipt::withTrashed()
			->select(
				'receipts.id',
				'receipts.code',
				'receipts.name',
				DB::raw('IF(receipts.mobile_no IS NULL,"--",receipts.mobile_no) as mobile_no'),
				DB::raw('IF(receipts.email IS NULL,"--",receipts.email) as email'),
				DB::raw('IF(receipts.deleted_at IS NULL,"Active","Inactive") as status')
			)
			->where('receipts.company_id', Auth::user()->company_id)
			->where(function ($query) use ($request) {
				if (!empty($request->receipt_code)) {
					$query->where('receipts.code', 'LIKE', '%' . $request->receipt_code . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->receipt_name)) {
					$query->where('receipts.name', 'LIKE', '%' . $request->receipt_name . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->mobile_no)) {
					$query->where('receipts.mobile_no', 'LIKE', '%' . $request->mobile_no . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->email)) {
					$query->where('receipts.email', 'LIKE', '%' . $request->email . '%');
				}
			})
			->orderby('receipts.id', 'desc');

		return Datatables::of($receipt_list)
			->addColumn('code', function ($receipt_list) {
				$status = $receipt_list->status == 'Active' ? 'green' : 'red';
				return '<span class="status-indicator ' . $status . '"></span>' . $receipt_list->code;
			})
			->addColumn('action', function ($receipt_list) {
				$edit_img = asset('public/theme/img/table/cndn/edit.svg');
				$delete_img = asset('public/theme/img/table/cndn/delete.svg');
				return '
					<a href="#!/receipt-pkg/receipt/edit/' . $receipt_list->id . '">
						<img src="' . $edit_img . '" alt="View" class="img-responsive">
					</a>
					<a href="javascript:;" data-toggle="modal" data-target="#delete_receipt"
					onclick="angular.element(this).scope().deleteReceipt(' . $receipt_list->id . ')" dusk = "delete-btn" title="Delete">
					<img src="' . $delete_img . '" alt="delete" class="img-responsive">
					</a>
					';
			})
			->make(true);
	}

	public function getReceiptFormData($id = NULL) {
		if (!$id) {
			$receipt = new Receipt;
			$address = new Address;
			$action = 'Add';
		} else {
			$receipt = Receipt::withTrashed()->find($id);
			$address = Address::where('address_of_id', 24)->where('entity_id', $id)->first();
			if (!$address) {
				$address = new Address;
			}
			$action = 'Edit';
		}
		$this->data['country_list'] = $country_list = Collect(Country::select('id', 'name')->get())->prepend(['id' => '', 'name' => 'Select Country']);
		$this->data['receipt'] = $receipt;
		$this->data['address'] = $address;
		$this->data['action'] = $action;

		return response()->json($this->data);
	}

	public function saveReceipt(Request $request) {
		// dd($request->all());
		try {
			$error_messages = [
				'code.required' => 'Receipt Code is Required',
				'code.max' => 'Maximum 255 Characters',
				'code.min' => 'Minimum 3 Characters',
				'name.required' => 'Receipt Name is Required',
				'name.max' => 'Maximum 255 Characters',
				'name.min' => 'Minimum 3 Characters',
				'gst_number.required' => 'GST Number is Required',
				'gst_number.max' => 'Maximum 191 Numbers',
				'mobile_no.max' => 'Maximum 25 Numbers',
				// 'email.required' => 'Email is Required',
				'address_line1.required' => 'Address Line 1 is Required',
				'address_line1.max' => 'Maximum 255 Characters',
				'address_line1.min' => 'Minimum 3 Characters',
				'address_line2.max' => 'Maximum 255 Characters',
				'pincode.required' => 'Pincode is Required',
				'pincode.max' => 'Maximum 6 Characters',
				'pincode.min' => 'Minimum 6 Characters',
			];
			$validator = Validator::make($request->all(), [
				'code' => 'required|max:255|min:3',
				'name' => 'required|max:255|min:3',
				'gst_number' => 'required|max:191',
				'mobile_no' => 'nullable|max:25',
				// 'email' => 'nullable',
				'address_line1' => 'required|max:255|min:3',
				'address_line2' => 'max:255',
				'pincode' => 'required|max:6|min:6',
			], $error_messages);
			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()->all()]);
			}

			DB::beginTransaction();
			if (!$request->id) {
				$receipt = new Receipt;
				$receipt->created_by_id = Auth::user()->id;
				$receipt->created_at = Carbon::now();
				$receipt->updated_at = NULL;
				$address = new Address;
			} else {
				$receipt = Receipt::withTrashed()->find($request->id);
				$receipt->updated_by_id = Auth::user()->id;
				$receipt->updated_at = Carbon::now();
				$address = Address::where('address_of_id', 24)->where('entity_id', $request->id)->first();
			}
			$receipt->fill($request->all());
			$receipt->company_id = Auth::user()->company_id;
			if ($request->status == 'Inactive') {
				$receipt->deleted_at = Carbon::now();
				$receipt->deleted_by_id = Auth::user()->id;
			} else {
				$receipt->deleted_by_id = NULL;
				$receipt->deleted_at = NULL;
			}
			$receipt->gst_number = $request->gst_number;
			$receipt->save();

			if (!$address) {
				$address = new Address;
			}
			$address->fill($request->all());
			$address->company_id = Auth::user()->company_id;
			$address->address_of_id = 24;
			$address->entity_id = $receipt->id;
			$address->address_type_id = 40;
			$address->name = 'Primary Address';
			$address->save();

			DB::commit();
			if (!($request->id)) {
				return response()->json(['success' => true, 'message' => ['Receipt Details Added Successfully']]);
			} else {
				return response()->json(['success' => true, 'message' => ['Receipt Details Updated Successfully']]);
			}
		} catch (Exceprion $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}
	public function deleteReceipt($id) {
		$delete_status = Receipt::withTrashed()->where('id', $id)->forceDelete();
		if ($delete_status) {
			$address_delete = Address::where('address_of_id', 24)->where('entity_id', $id)->forceDelete();
			return response()->json(['success' => true]);
		}
	}
}
