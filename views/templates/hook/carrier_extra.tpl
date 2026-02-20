<div class="form-group row bookurier-locker-selector" data-save-url="{$bookurier_locker_save_url|escape:'htmlall':'UTF-8'}">
  <label class="col-form-label col-12 col-md-3 form-control-label bookurier-locker-label" for="bookurier-locker-select">
    {l s='Choose locker' mod='bookurier'}
  </label>

  <div class="col-12 col-md-9">
    <select id="bookurier-locker-select" class="bookurier-locker-select" name="bookurier_locker_id">
      <option value="">{l s='Select a locker...' mod='bookurier'}</option>
      {foreach from=$bookurier_lockers item=locker}
        <option
          value="{$locker.locker_id|intval}"
          {if $bookurier_selected_locker_id|intval === $locker.locker_id|intval}selected="selected"{/if}
        >
          {$locker.label|escape:'htmlall':'UTF-8'}
        </option>
      {/foreach}
    </select>

    <p class="bookurier-locker-status form-text text-muted" aria-live="polite"></p>
  </div>
</div>
