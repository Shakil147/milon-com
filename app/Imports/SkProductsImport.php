<?php

namespace App\Imports;

use Auth;
use Storage;
use App\Models\User;
use App\Models\Brand;
use App\Models\Upload;
use App\Models\Product;
use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Support\Str;
use App\Models\ProductStock;
use App\Models\AttributeValue;
use App\Models\BrandTranslation;
use Illuminate\Support\Collection;
use App\Models\CategoryTranslation;
use App\Models\AttributeTranslation;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SkProductsImport implements ToCollection, WithHeadingRow, WithValidation, ToModel
{
    private $rows = 0;

    public function collection(Collection $rows) {

         $single_products = $rows->whereNotNull('title');
         $option1_names = array_unique(
            $rows->whereNotNull('option1_name')->pluck('option1_name')->toArray()
         );
         $options = $this->getAttrebitue($option1_names,$rows);

        $canImport = true;
        if (addon_is_activated('seller_subscription')){
            if(Auth::user()->user_type == 'seller' && Auth::user()->seller->seller_package && (count($rows) + Auth::user()->seller->user->products()->count()) > Auth::user()->seller->seller_package->product_upload_limit) {
                $canImport = false;
                flash(translate('Upload limit has been reached. Please upgrade your package.'))->warning();
            }
        }

        if($canImport) {
            foreach ($single_products as $key => $row) {
                dd($row);
                $arrarr_images = $rows->where('handle',$row['handle'])->whereNotNull('image_src')->pluck('image_src')->toArray();
                $arrarr_option1_name = $rows->where('handle',$row['handle'])
                ->whereNotNull('option1_name')
                ->where('option1_name','!=','Title')
                ->pluck('option1_name')->toArray();
               // dd($arrarr_option1_name);

                $attribute_ids = Attribute::query()->whereIn('name',$arrarr_option1_name )->get()->pluck('id')->toArray();
				$approved = 1;

                 [$ids,$productOptions] = $this->productOptions($row,$rows);


                $catehoryId = $this->getCategoryId($row['vendor'] ?? 'Demo brand');
                $brand_id = $this->getVendorId($row['type'] ?? 'Demo category 1');
                $productId = Product::create([
                            'name' => $row['title'],
                            'description' => $row['body_html'],
                            'added_by' => Auth::user()->user_type == 'seller' ? 'seller' : 'admin',
                            'user_id' => Auth::user()->user_type == 'seller' ? Auth::user()->id : User::where('user_type', 'admin')->first()->id,
                            'approved' => $approved,
							'category_id' => $catehoryId,
                            'brand_id' => $brand_id,
                            'video_provider' => 'youtube',
                            'video_link' => '',
                            'unit_price' => $row['variant_price'],
                            'purchase_price' => 0,
                            'unit' => 'pc',
                            'meta_title' => '',
                            'meta_description' => '',
                            'colors' => json_encode(array()),
                            'variations' => json_encode(array()),
                            'tags' => $row['tags'],
                            'choice_options' =>  json_encode($productOptions),
                            'slug' => preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($row['title']))) . '-' . Str::random(5),
                            'thumbnail_img' => $this->downloadThumbnail($row['image_src']),
                            'photos' => $this->downloadGalleryImages($arrarr_images),
                ]);
                $productId->attributes = json_encode($attribute_ids);
                $productId->variant_product = count($attribute_ids) > 0 ? 1 : 0;
                $productId->save();
                if(count($productOptions)){
                    foreach($productOptions as $po){

                        foreach($po['values'] as $val){

                           $ps =  ProductStock::create([
                                'product_id' => $productId->id,
                                'qty' => 1000,
                                'price' => $row['variant_price'],
                                'variant' => str_replace(' ', '',$val),
                            ]);
                        }
                    }

                }else{
                    ProductStock::create([
                        'product_id' => $productId->id,
                        'qty' => 1000,
                        'price' => $row['variant_price'],
                        'variant' => '',
                    ]);
                }

            }

            flash(translate('Products imported successfully'))->success();
        }


    }



    public function newProduct($row){

    }
    public function model(array $row)
    {
        ++$this->rows;
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }

    public function rules(): array
    {
        return [
             // Can also use callback validation rules
             'unit_price' => function($attribute, $value, $onFailure) {
                  if (!is_numeric($value)) {
                       $onFailure('Unit price is not numeric');
                  }
              }
        ];
    }

    public function downloadThumbnail($url){
        try {
            $upload = new Upload;
            $upload->external_link = $url;
            $upload->save();

            return $upload->id;
        } catch (\Exception $e) {

        }
        return null;
    }

    public function downloadGalleryImages($urls){
        $data = array();
        foreach($urls as $url){
            $data[] = $this->downloadThumbnail($url);
        }
        return implode(',', $data);
    }

    public function getAttrebitue($array,$rows){
        if(count($array) == 0){
            return collect([]);
        }
        foreach($array as $name){

            $attribute = Attribute::query()
                    ->where('name',$name)
                    ->first();

            if(!$attribute){
                $attribute = new Attribute;
                $attribute->name = $name;
                $attribute->save();

                $attribute_translation = AttributeTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'attribute_id' => $attribute->id]);
                $attribute_translation->name = $name;
                $attribute_translation->save();
            }

           $values =  array_unique(
                $rows->where('option1_name',$name)->pluck('option1_value')->toArray()
            );

            if(count($values) >0){
                foreach($values as $value){

                    $attribute_value = AttributeValue::query()
                    ->where('attribute_id',$attribute->id)
                    ->where('value',ucfirst($value))
                    ->first();
                    if(!$attribute_value){
                        $attribute_value = new AttributeValue;
                        $attribute_value->attribute_id = $attribute->id;
                        $attribute_value->value = ucfirst($value);
                        $attribute_value->save();
                    }
                }
            }

        }
        return Attribute::query()->with('attribute_values')->get();

    }
    public function getCategoryId($name)
    {
        $category = Category::query()->where('name',$name)->first();
        if(!$category){
            $category = new Category;
            $category->name = $name;
            $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $name)).'-'.Str::random(5);;
            $category->order_level = 0;
            $category->save();

            $category_translation = CategoryTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'category_id' => $category->id]);
            $category_translation->name = $name;
            $category_translation->save();
        }
        return $category->id;

    }


    public function getVendorId($name)
    {
        $brand = Brand::query()->where('name',$name)->first();
        if(!$brand){
            $brand = new Brand;
            $brand->name = $name;
            $brand->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $name)).'-'.Str::random(5);;
            $brand->save();

            $brand_translation = BrandTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'brand_id' => $brand->id]);
            $brand_translation->name = $name;
            $brand_translation->save();
        }
        return $brand->id;

    }

    public function productOptions($row,$rows)
    {
        $data = $rows->where('handle',$row['handle'])
        ->whereNotNull('option1_name')
        ->whereNotNull('option1_value');
        if(count($data)){
            $option  = [] ;
            foreach($data as $op){
                if($op['option1_name'] == 'Title'){
                    continue;
                }
                $attribute = Attribute::query()->where('name',$op['option1_name'])->first();

                    $attribute_value = AttributeValue::query()
                    ->where('attribute_id',$attribute->id)
                    ->where('value',ucfirst($op['option1_value']))
                    ->first();
                    if(!$attribute_value){
                        $attribute_value = new AttributeValue;
                        $attribute_value->attribute_id = $attribute->id;
                        $attribute_value->value = ucfirst($op['option1_value']);
                        $attribute_value->save();
                    }


                $option[$attribute->id] = ['attribute_id'=>$attribute->id,'values'=>$attribute_value->value];



            }
            $x_option = collect($option);
            $result = [];
            $ids = [];
            foreach($x_option->pluck('attribute_id')->toArray() as $i){

                $ids = array_push($ids,$i);
                $result[] = [
                    'attribute_id'=>$i,
                    'values'=> $x_option->where('attribute_id',$i)->pluck('values')->toArray(),
                ];
            }
            $result = collect($result);

            return [
                array_unique($result->pluck('attribute_id')->toArray()),
                collect($result),
            ];
        }else{
            return [
                [],
                collect([])
            ];
        }
    }
}
