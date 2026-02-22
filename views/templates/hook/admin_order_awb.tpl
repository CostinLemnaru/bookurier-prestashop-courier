{if !empty($bookurier_awb_is_legacy_ui)}
<div class="panel panel-default bookurier-awb-panel">
  <div class="panel-heading">
    <strong>{$bookurier_awb_title|escape:'html':'UTF-8'}</strong>
  </div>
  <div class="panel-body">
{else}
<div class="card mt-2 bookurier-awb-panel">
  <div class="card-header">
    <h3 class="card-header-title">{$bookurier_awb_title|escape:'html':'UTF-8'}</h3>
  </div>
  <div class="card-body">
{/if}
    {if !empty($bookurier_awb_code)}
      <p>
        <strong>{$bookurier_awb_code_label|escape:'html':'UTF-8'}:</strong>
        {$bookurier_awb_code|escape:'html':'UTF-8'}
      </p>
      {if !empty($bookurier_awb_status)}
        <p>
          <strong>{$bookurier_awb_status_label|escape:'html':'UTF-8'}:</strong>
          {$bookurier_awb_status|escape:'html':'UTF-8'}
        </p>
      {/if}
    {else}
      <p>{$bookurier_awb_empty_label|escape:'html':'UTF-8'}</p>
    {/if}
    {if !empty($bookurier_awb_download_url)}
      <p>
        <a class="btn btn-primary" href="{$bookurier_awb_download_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {$bookurier_awb_download_label|escape:'html':'UTF-8'}
        </a>
      </p>
    {/if}
    {if !empty($bookurier_awb_generate_url)}
      <p>
        <button
          type="button"
          class="btn btn-default js-bookurier-generate-awb"
          data-url="{$bookurier_awb_generate_url|escape:'html':'UTF-8'}"
          data-order-id="{$bookurier_awb_order_id|intval}"
          data-loading-label="{$bookurier_awb_generating_label|escape:'html':'UTF-8'}"
        >
          {$bookurier_awb_generate_label|escape:'html':'UTF-8'}
        </button>
      </p>
      <script>
        (function () {
          var selector = '.js-bookurier-generate-awb[data-order-id="{$bookurier_awb_order_id|intval}"]';
          var button = document.querySelector(selector);
          if (!button || button.getAttribute('data-bound') === '1') {
            return;
          }

          button.setAttribute('data-bound', '1');
          button.addEventListener('click', function () {
            if (button.disabled) {
              return;
            }

            var originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = button.getAttribute('data-loading-label') || originalLabel;

            fetch(button.getAttribute('data-url'), {
              method: 'GET',
              credentials: 'same-origin'
            })
              .then(function (response) {
                return response.json().catch(function () {
                  return { success: false, message: 'Invalid response.' };
                });
              })
              .then(function (payload) {
                if (payload && payload.success) {
                  window.location.reload();
                  return;
                }

                alert(payload && payload.message ? payload.message : 'Could not generate AWB.');
                button.disabled = false;
                button.textContent = originalLabel;
              })
              .catch(function () {
                alert('Could not generate AWB.');
                button.disabled = false;
                button.textContent = originalLabel;
              });
          });
        })();
      </script>
    {/if}
  </div>
</div>

{if !empty($bookurier_awb_is_legacy_ui)}
  <style>
    .bookurier-awb-panel {
      margin-top: 12px;
    }
    .bookurier-awb-panel .panel-body {
      padding: 14px !important;
    }
    .bookurier-awb-panel p {
      margin: 0 0 10px;
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    .bookurier-awb-panel .btn {
      white-space: normal;
    }
  </style>
{/if}
