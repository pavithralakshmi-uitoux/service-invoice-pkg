<?php

namespace Abs\ServiceInvoicePkg;
use Abs\AttributePkg\FieldGroup;
use Abs\CoaPkg\CoaCode;
use Abs\TaxPkg\TaxCode;
use App\Company;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceItem extends Model {
	use SoftDeletes;
	protected $table = 'service_items';
	protected $fillable = [
		'created_by_id',
		'updated_by_id',
		'deleted_by_id',
	];

	public function fieldGroups() {
		return $this->belongsToMany('Abs\AttributePkg\FieldGroup', 'service_item_field_group', 'service_item_id', 'field_group_id');
	}

	public function coaCode() {
		return $this->belongsTo('Abs\CoaPkg\CoaCode', 'coa_code_id', 'id');
	}

	public function taxCode() {
		return $this->belongsTo('Abs\TaxPkg\TaxCode', 'sac_code_id', 'id');
	}
	public function subCategory() {
		return $this->belongsTo('Abs\ServiceInvoicePkg\ServiceItemSubCategory', 'sub_category_id', 'id');
	}

	public static function searchServiceItem($r) {
		$key = $r->key;
		$type_id = $r->type_id;
		$list = self::where('company_id', Auth::user()->company_id)
			->select(
				'id',
				'name',
				'code'
			);
		if ($type_id == 1061) {
			$list = $list->whereNotNull('sac_code_id');
		}
		if ($type_id == 1060 && session('sac_code_value') != 'new') {
			if (session('sac_code_value') == NULL) {
				$list = $list->whereNull('sac_code_id');
			} elseif (session('sac_code_value') != NULL) {
				$list = $list->whereNotNull('sac_code_id');
			}
		}
		$list = $list->where(function ($q) use ($key) {
			$q->where('name', 'like', '%' . $key . '%')
				->orWhere('code', 'like', '%' . $key . '%')
			;
		})
			->get();

		return response()->json($list);
	}

	public static function createFromCollection($records, $company = null) {
		foreach ($records as $key => $record_data) {
			try {
				if (!$record_data->company) {
					continue;
				}
				$record = self::createFromObject($record_data, $company);
			} catch (Exception $e) {
				dd($e);
			}
		}
	}

	public static function createFromObject($record_data, $company = null) {

		$errors = [];
		if (!$company) {
			$company = Company::where('code', $record_data->company)->first();
		}
		if (!$company) {
			dump('Invalid Company : ' . $record_data->company);
			return;
		}

		$admin = $company->admin();
		if (!$admin) {
			dump('Default Admin user not found');
			return;
		}

		$sub_category_id = null;

		if ($record_data->main_category) {
			$main_category = ServiceItemCategory::where('name', $record_data->main_category)->where('company_id', $company->id)->first();
			if (!$main_category) {
				$errors[] = 'Invalid main category : ' . $record_data->main_category;
			} else {
				if ($record_data->sub_category) {
					$sub_category = ServiceItemSubCategory::where([
						'name' => $record_data->sub_category,
						'company_id' => $company->id,
						'category_id' => $main_category->id,
					])->first();
					if (!$sub_category) {
						$errors[] = 'Invalid sub category : ' . $record_data->sub_category;
					} else {
						$sub_category_id = $sub_category->id;

					}
				}
			}
		}

		$coa_code_id = null;
		if ($record_data->coa_code) {
			$coa_code = CoaCode::where('code', $record_data->coa_code)->where('company_id', $company->id)->first();
			if (!$coa_code) {
				$errors[] = 'Invalid coa code : ' . $record_data->coa_code;
			} else {
				$coa_code_id = $coa_code->id;

			}
		}

		$sac_code_id = null;
		if ($record_data->sac_code) {
			$sac_code = TaxCode::where('code', $record_data->sac_code)->where('type_id', 1021)->where('company_id', $company->id)->first();
			if (!$sac_code) {
				$errors[] = 'Invalid sac code : ' . $record_data->sac_code;
			} else {
				$sac_code_id = $sac_code->id;
			}
		}

		if (count($errors) > 0) {
			dump($errors);
			return;
		}

		$record = self::firstOrNew([
			'company_id' => $company->id,
			'code' => $record_data->code,
		]);
		$record->name = $record_data->name;
		$record->sub_category_id = $sub_category_id;
		$record->coa_code_id = $coa_code_id;
		$record->sac_code_id = $sac_code_id;
		$record->created_by_id = $admin->id;
		$record->save();
		return $record;
	}

	public static function mapFieldGroups($records, $company = null) {
		foreach ($records as $key => $record_data) {
			try {
				if (!$record_data->company) {
					continue;
				}
				$record = self::mapFieldGroup($record_data, $company);
			} catch (Exception $e) {
				dd($e);
			}
		}
	}
	public static function mapFieldGroup($record_data, $company = null) {

		$errors = [];
		if (!$company) {
			$company = Company::where('code', $record_data->company)->first();
		}
		if (!$company) {
			dump('Invalid Company : ' . $record_data->company);
			return;
		}

		$admin = $company->admin();
		if (!$admin) {
			dump('Default Admin user not found');
			return;
		}

		$record = ServiceItem::where('code', $record_data->service_item)->where('company_id', $company->id)->first();
		if (!$record) {
			$errors[] = 'Invalid Service Item : ' . $record_data->service_item;
		}

		$field_group = FieldGroup::where('name', $record_data->field_group_name)->where('company_id', $company->id)->first();
		if (!$field_group) {
			$errors[] = 'Invalid field group : ' . $record_data->field_group;
		}

		if (count($errors) > 0) {
			dump($errors);
			return;
		}

		$record->fieldGroups()->syncWithoutDetaching([$field_group->id]);
		return $record;
	}

}
