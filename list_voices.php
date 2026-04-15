<?php
/**
 * iFlytek TTS Voices List (Console version)
 */

$voices = [
    ["vcn" => "xiaoyan", "name" => "Xiaoyan", "gender" => "Ayol", "info" => "Multilingual (Tavsiya)"],
    ["vcn" => "aisjiaying", "name" => "Jiaying", "gender" => "Ayol", "info" => "Premium Multilingual"],
    ["vcn" => "aisxping", "name" => "Xiping", "gender" => "Erkak", "info" => "Multilingual Erkak"],
    ["vcn" => "x2_RuRu_Keshu", "name" => "Keshu", "gender" => "Ayol", "info" => "Russian/Multilingual"],
    ["vcn" => "rania", "name" => "Rania", "gender" => "Ayol", "info" => "Arabic/Multilingual"],
    ["vcn" => "mohamed", "name" => "Mohamed", "gender" => "Erkak", "info" => "Arabic"],
    ["vcn" => "x4_EnUk_Lizzy_assist", "name" => "Lizzy", "gender" => "Ayol", "info" => "UK English"],
    ["vcn" => "aisjinger", "name" => "Jinger", "gender" => "Ayol", "info" => "Lively"],
    ["vcn" => "aisbabyxu", "name" => "Baby Xu", "gender" => "Ayol", "info" => "Child"],
];

echo "\n" . str_repeat("-", 80) . "\n";
echo sprintf("%-25s | %-15s | %-10s | %-20s\n", "VCN (ID)", "NOMI", "JINSI", "MA'LUMOT");
echo str_repeat("-", 80) . "\n";

foreach ($voices as $v) {
    echo sprintf("%-25s | %-15s | %-10s | %-20s\n", $v['vcn'], $v['name'], $v['gender'], $v['info']);
}

echo str_repeat("-", 80) . "\n";
echo "💡 O'zbek tili uchun 'Multilingual' ovozlar tavsiya etiladi.\n\n";
