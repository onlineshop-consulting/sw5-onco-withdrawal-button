{extends file='parent:frontend/forms/form-elements.tpl'}

{block name='frontend_forms_form_elements_form_submit'}
    {if $sSupport.attribute.oncoWithdrawalIsWithdrawalForm}
        {block name="onco_withdrawal_form_button"}
            <div class="buttons">
                <button class="btn is--primary is--icon-right" type="submit" name="Submit" value="submit">
                    {s name='FormButton' namespace='frontend/plugins/onco_withdrawal'}Confirm withdrawal{/s}
                    <i class="icon--arrow-right"></i>
                </button>
            </div>
        {/block}
    {else}
        {$smarty.block.parent}
    {/if}

    {block name="onco_withdrawal_prefill_script"}
        {if !empty($oncoWithdrawalPrefill)}
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var data = {$oncoWithdrawalPrefill|json_encode};
                    var form = document.querySelector('form.content--form-wrapper') || document.querySelector('.forms--content form');
                    if (!form) return;

                    Object.keys(data).forEach(function(name) {
                        var value = data[name];
                        if (!value) return;

                        var field = form.querySelector('[name="' + name + '"]');
                        if (!field) return;

                        if (field.tagName === 'SELECT') {
                            if (field.value) return;
                            var matched = Array.prototype.some.call(field.options, function(opt) {
                                if (opt.value === value) { field.value = value; return true; }
                            });
                            if (!matched) {
                                Array.prototype.some.call(field.options, function(opt) {
                                    if (opt.textContent.trim() === value) { field.value = opt.value; return true; }
                                });
                            }
                        } else {
                            if (field.value) return;
                            field.value = value;
                        }
                    });
                });
            </script>
        {/if}
    {/block}
{/block}
