<?php
namespace App\Services;

use App\Models\{User, Test, Culture, TestGroup, Package, PriceListRel, Referal, Lab};

/** Shared invoice form receives only this laboratory's orderable definitions. */
class CollectorCatalog
{
    public const KINDS = ['tests'=>[Test::class,'test_id_fk'], 'cultures'=>[Culture::class,'culture_id_fk'], 'test_groups'=>[TestGroup::class,'test_group_id_fk'], 'packages'=>[Package::class,'package_id_fk']];
    public function all(User $lab, ?int $source): array
    {
        $ids = app(CollectorAccess::class)->accounts($lab);
        $connection = $source ? Referal::whereIn('lab_id_fk',$ids)->where('referral_id_fk',$source)->first() : null;
        $linked = $source ? Lab::whereIn('parent_lab_id_fk',$ids)->where('user_id_fk',$source)->first() : null;
        $listId = $linked?->price_list_id_fk ?? $connection?->price_list_id_fk;
        $list = $listId ? \App\Models\PriceList::whereIn('lab_id_fk',$ids)->find($listId) : null;
        $prices = $list ? PriceListRel::whereIn('lab_id_fk',$ids)->where('price_list_id_fk',$list->id)->get() : collect();
        $all=[];
        foreach (self::KINDS as $kind=>[$class,$field]) {
            $relations = $kind==='packages' ? ['tests.sample','cultures.sample','testGroups.tests.sample','testGroups.culture.sample'] : ($kind==='test_groups' ? ['tests.sample','culture.sample'] : ['sample']);
            $all[$kind] = $class::whereIn('lab_id_fk',$ids)->with($relations)->get()->map(function($m) use($kind,$field,$prices,$list,$ids) {
                $priceRow=$prices->firstWhere($field,$m->id);
                $base=$this->retail($m,$kind);
                if($kind==='test_groups' && $base<=0) {
                    $base=$m->tests->filter(fn($t)=>!$t->trashed()&&in_array((int)$t->lab_id_fk,$ids))->sum(fn($t)=>$this->retail($t,'tests'))
                        +$m->culture->filter(fn($t)=>!$t->trashed()&&in_array((int)$t->lab_id_fk,$ids))->sum(fn($t)=>$this->retail($t,'cultures'));
                }
                $price=$priceRow ? ($priceRow->price_for_customer ?? $priceRow->original_price)*(1-max(0,min(100,(float)$list->discount))/100) : $base;
                $d=['id'=>$m->id,'name'=>$kind==='test_groups'?$m->group_name:$m->name,'group_name'=>$kind==='test_groups'?$m->group_name:null,'type'=>$kind==='test_groups'?'group':'test','shortcut'=>$m->getAttributes()['shortcut']??null,'price'=>(int)round($price??0),'for_customer_price'=>(int)round($price??0),'original_price'=>(int)round($price??0),'prices'=>[]];
                $brief=fn($t)=>['id'=>$t->id,'name'=>$t->name,'sample_name'=>$t->sample?->sample_name,'sample_id_fk'=>$t->sample_id_fk,'price'=>0,'for_customer_price'=>0];
                if(in_array($kind,['packages','test_groups'])) {
                    $tests=$m->tests; $cultures=$kind==='packages'?$m->cultures:$m->culture;
                    if($kind==='packages') { $tests=$tests->merge($m->testGroups->flatMap(fn($g)=>$g->tests))->unique('id'); $cultures=$cultures->merge($m->testGroups->flatMap(fn($g)=>$g->culture))->unique('id'); }
                    // A malformed bundle cannot smuggle another laboratory's analyses.
                    $valid=fn($t)=>!$t->trashed() && in_array((int)$t->lab_id_fk,$ids);
                    $d['tests']=$tests->filter($valid)->map($brief)->values()->all();
                    $d['cultures']=$cultures->filter($valid)->map($brief)->values()->all(); $d['culture']=$d['cultures'];
                } else { $d['sample_name']=$m->sample?->sample_name; $d['sample_id_fk']=$m->sample_id_fk; }
                return $d;
            })->keyBy('id')->all();
        }
        return $all;
    }
    private function retail($model,string $kind): float
    {
        $a=$model->getAttributes();
        $customer=$a[$kind==='cultures'?'price_for_customer':'for_customer_price']??null;
        return (float)($customer>0?$customer:($a['price']??$a['original_price']??0));
    }
    public function questions(User $lab, array $ids): array
    {
        $questions=[];
        $tests=Test::whereIn('lab_id_fk',app(CollectorAccess::class)->accounts($lab))->whereIn('id',$ids)->with('questions.answerType')->get();
        abort_unless($tests->count()===count(array_unique($ids)),422,'تحليل غير متاح.');
        foreach($tests as $test) foreach($test->questions as $q) $questions[]=['id'=>$q->id,'test_id_fk'=>$test->id,'question'=>$q->question,'answer_type'=>$q->answerType?->answer??'text','answer_type_id_fk'=>$q->answer_type_id_fk,'answer_type_selection_values'=>$q->answer_type_selection_values];
        return $questions;
    }
}
