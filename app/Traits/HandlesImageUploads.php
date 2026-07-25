<?php
 
namespace App\Traits;
 
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Log;
 
trait HandlesImageUploads
 {
     /**
      * Process image fields before filling the model.
      *
      * @param array $attributes
      * @return array Processed attributes with image paths instead of base64
      */
     protected function processImageFields(array $attributes): array
     {
         if (!method_exists($this, 'getFieldModelManager')) {
             return $attributes;
         }
 
         $imageService = app(ImageUploadService::class);
         $fieldManager = $this->getFieldModelManager();
         $fields = $fieldManager->getFields();
 
         foreach ($attributes as $fieldName => $value) {
             $field = $fields[$fieldName] ?? null;
             
             if (!$field) {
                 continue;
             }
 
             // Check if this is an image field
             $fieldType = strtolower($field->getFieldType());
             if ($fieldType !== 'image') {
                 continue;
             }
 
             // Extract the path if it is a pre-existing image structure
             if (is_array($value) && isset($value['meta']['path'])) {
                 $value = $value['meta']['path'];
                 $attributes[$fieldName] = $value;
             }
 
             // Check if value contains base64 image data
             if (!$imageService->isBase64ImageData($value)) {
                 // Not base64 data, might be an existing path or empty - skip processing
                 continue;
             }
 
             try {
                 // Get old image path for cleanup if updating
                 $oldImagePath = $this->exists && isset($this->attributes[$fieldName]) 
                     ? $this->attributes[$fieldName] 
                     : null;
 
                 // Get record ID (use existing ID or null for new records)
                 $recordId = $this->exists ? $this->id : null;
 
                 // Process and save the image to Google Drive
                 $result = $imageService->processAndSave($value, $recordId, $oldImagePath);
 
                 // Replace base64 data with Google Drive file ID
                 $attributes[$fieldName] = $result['path'];
 
                 Log::info('Image field processed and saved to Google Drive', [
                     'module' => $this->getModuleName(),
                     'field' => $fieldName,
                     'file_id' => $result['path'],
                     'url' => $result['url'],
                 ]);
 
             } catch (\Exception $e) {
                 Log::error('Failed to process image field', [
                     'module' => $this->getModuleName(),
                     'field' => $fieldName,
                     'error' => $e->getMessage(),
                 ]);
                 
                 throw $e;
             }
         }
 
         return $attributes;
     }
 
     /**
      * Transform image paths (Google Drive file IDs) to public URLs for API responses.
      *
      * @param array $data
      * @return array
      */
     protected function transformImageFieldsToUrls(array $data): array
     {
         if (!method_exists($this, 'getFieldModelManager')) {
             return $data;
         }
 
         $imageService = app(ImageUploadService::class);
         $fieldManager = $this->getFieldModelManager();
         $fields = $fieldManager->getFields();
 
         foreach ($data as $fieldName => $value) {
             $field = $fields[$fieldName] ?? null;
             
             if (!$field) {
                 continue;
             }
 
             // Check if this is an image field
             $fieldType = strtolower($field->getFieldType());
             if ($fieldType !== 'image') {
                 continue;
             }
 
             // Transform stored Google Drive file ID to public URL
             if (is_string($value) && !empty($value)) {
                 $data[$fieldName] = [
                     'type' => 'image',
                     'value' => $imageService->transformToUrl($value),
                     'meta' => [
                         'path' => $value,
                     ],
                 ];
             }
         }
 
         return $data;
     }
 
     /**
      * Delete image files from Google Drive when a record is deleted.
      */
     protected function deleteImageFilesOnDelete(): void
     {
         if (!method_exists($this, 'getFieldModelManager')) {
             return;
         }
 
         $imageService = app(ImageUploadService::class);
         $fieldManager = $this->getFieldModelManager();
         $fields = $fieldManager->getFields();
 
         foreach ($fields as $fieldName => $field) {
             $fieldType = strtolower($field->getFieldType());
             if ($fieldType !== 'image') {
                 continue;
             }
 
             $imagePath = $this->attributes[$fieldName] ?? null;
             if ($imagePath) {
                 $imageService->deleteImage($imagePath);
             }
         }
     }
 }
