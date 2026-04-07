{extends file='parent:frontend/index/index.tpl'}

{block name="frontend_index_shopware_footer"}
    {block name="onco_withdrawal_footer"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.showInFooter}
            <div class="onco-withdrawal-global-btn-container footer-position">
                {include file="plugin/onco_withdrawal/global_button.tpl"}
            </div>
        {/if}
    {/block}
    {$smarty.block.parent}

    {block name="onco_withdrawal_custom_selector_template"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.customSelector}
            <script type="text/html" id="onco-withdrawal-custom-btn-tpl">
                <div class="onco-withdrawal-global-btn-container is--custom-selector">
                    {include file="plugin/onco_withdrawal/global_button.tpl"}
                </div>
            </script>
        {/if}
    {/block}
{/block}

{block name="frontend_index_header_javascript_jquery_lib"}
    {$smarty.block.parent}

    {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.customSelector}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tpl = document.getElementById('onco-withdrawal-custom-btn-tpl');
                if (!tpl) return;

                var html = tpl.innerHTML;
                var method = '{$oncoWithdrawal.config.customSelectorPosition|default:'append'|escape:"javascript"}';

                document.querySelectorAll('{$oncoWithdrawal.config.customSelector|escape:"javascript"}').forEach(function(target) {
                    if (method === 'prepend') {
                        target.insertAdjacentHTML('afterbegin', html);
                    } else if (method === 'before') {
                        target.insertAdjacentHTML('beforebegin', html);
                    } else if (method === 'after') {
                        target.insertAdjacentHTML('afterend', html);
                    } else {
                        target.insertAdjacentHTML('beforeend', html);
                    }
                });
            });
        </script>
    {/if}
{/block}
