<?php

namespace App\Imports;

use App\Models\Brand;
use Auth;
use Storage;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;
use App\Models\ProductStock;
use App\Models\BrandTranslation;
use Illuminate\Support\Collection;
use App\Models\CategoryTranslation;
use App\Models\Upload;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SkProductsImport implements ToCollection, WithHeadingRow, WithValidation, ToModel
{
    private $rows = 0;

    public function collection(Collection $rows) {

         $single_products = $rows->whereNotNull('title');
        // $rows = $rows->whereNotNull('title');
      //  dd('shakil',$rows);
        $canImport = true;
        if (addon_is_activated('seller_subscription')){
            if(Auth::user()->user_type == 'seller' && Auth::user()->seller->seller_package && (count($rows) + Auth::user()->seller->user->products()->count()) > Auth::user()->seller->seller_package->product_upload_limit) {
                $canImport = false;
                flash(translate('Upload limit has been reached. Please upgrade your package.'))->warning();
            }
        }

        if($canImport) {
            foreach ($single_products as $row) {
                $arrarr_images = $rows->where('handle',$row['handle'])->whereNotNull('image_src')->pluck('image_src')->toArray();
				$approved = 1;


                $catehoryId = $this->getCategoryId($row['type'] ?? 'Demo category 1');
                $brand_id = $this->getVendorId($row['vendor'] ?? 'Demo brand');

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
                            'choice_options' => json_encode(array()),
                            'variations' => json_encode(array()),
                            'slug' => preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($row['title']))) . '-' . Str::random(5),
                            'thumbnail_img' => $this->downloadThumbnail($row['image_src']),
                            'photos' => $this->downloadGalleryImages($arrarr_images),
                ]);
                ProductStock::create([
                    'product_id' => $productId->id,
                    'qty' => 1,
                    'price' => $row['variant_price'],
                    'variant' => '',
                ]);
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
}
