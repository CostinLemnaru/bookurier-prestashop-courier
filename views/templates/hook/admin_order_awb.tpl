<div class="card mt-2">
  <div class="card-header">
    <h3 class="card-header-title">{$bookurier_awb_title|escape:'html':'UTF-8'}</h3>
  </div>
  <div class="card-body">
    <p>
      <strong>{$bookurier_awb_code_label|escape:'html':'UTF-8'}:</strong>
      {$bookurier_awb_code|escape:'html':'UTF-8'}
    </p>
    {if !empty($bookurier_awb_download_url)}
      <p>
        <a class="btn btn-primary" href="{$bookurier_awb_download_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {$bookurier_awb_download_label|escape:'html':'UTF-8'}
        </a>
      </p>
    {/if}
  </div>
</div>
