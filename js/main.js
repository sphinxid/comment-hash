// main.js
(function($) {
    'use strict';

    let worker = null;
    let challengeData = null;
    let $form = null;
    let formData = null;

    function initWorker() {
        if (worker) {
            worker.terminate();
        }
        worker = new Worker(commentHashSettings.workerUrl);
        
        worker.addEventListener('message', function(e) {
            const { type, nonce, error } = e.data;
            
            if (type === 'success') {
                $('#comment-hash-status').html('✓ Verification complete');
                
                // Add the proof-of-work data to the form
                $('#comment-pow-nonce').val(nonce);
                $('#comment-pow-challenge').val(challengeData.challenge);
                $('#comment-pow-unique-str').val(challengeData.uniqueStr);
                $('#comment-pow-timestamp').val(challengeData.timestamp);
                $('#comment-pow-digest').val(challengeData.digest);
                
                // Log the data being submitted for debugging
                console.log('Submitting PoW data:', {
                    nonce,
                    challenge: challengeData.challenge,
                    uniqueStr: challengeData.uniqueStr,
                    timestamp: challengeData.timestamp,
                    digest: challengeData.digest
                });
                
                // Actually submit the form
                submitForm();
            } else if (type === 'error') {
                $('#comment-hash-status').html('Error: ' + error);
                $('#comment-hash-progress').hide();
                enableForm();
            } else if (type === 'progress') {
                // You could update a progress indicator here if desired
            }
        });
    }

    function submitForm() {
        // Remove our submit event handler temporarily
        $form.off('submit.comment-hash');
        
        // Get the form's submit button
        const submitButton = $form.find(':submit');
        
        // Create a temporary button, click it, and remove it
        // This is more reliable than calling form.submit() as it triggers all form events
        const tempButton = $('<input type="submit">')
            .hide()
            .appendTo($form);
            
        tempButton.trigger('click');
        tempButton.remove();
        
        // Re-enable the form
        enableForm();
        
        // Reattach our submit handler
        setupFormHandler();
    }

    function disableForm() {
        $('#submit').prop('disabled', true);
        $('#comment-hash-overlay').show();
    }

    function enableForm() {
        $('#submit').prop('disabled', false);
        $('#comment-hash-overlay').hide();
    }

    async function getChallenge() {
        try {
            const response = await $.ajax({
                url: commentHashSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_comment_challenge'
                }
            });

            if (response.success && response.data) {
                return response.data;
            } else {
                throw new Error('Invalid server response');
            }
        } catch (error) {
            console.error('Error getting challenge:', error);
            throw error;
        }
    }

    function setupFormHandler() {
        // Handle form submission
        $form.on('submit.comment-hash', async function(e) {
            e.preventDefault();
            
            try {
                disableForm();
                
                // Get challenge from server
                challengeData = await getChallenge();
                
                // Initialize Web Worker
                initWorker();
                
                // Start proof-of-work computation
                // Log the challenge data for debugging
                console.log('Challenge data received:', challengeData);
                
                worker.postMessage({
                    challenge: challengeData.challenge,
                    uniqueStr: challengeData.uniqueStr,
                    timestamp: challengeData.timestamp,
                    difficulty: commentHashSettings.difficulty,
                    nonceRange: commentHashSettings.nonceRange
                });
                
            } catch (error) {
                console.error('Error during comment submission:', error);
                $('#comment-hash-status').html('Error: Please try again');
                enableForm();
            }
        });
    }

    function setupCommentForm() {
        // Store the form reference
        $form = $('#commentform');
        
        // Add necessary hidden fields to the comment form
        $form.append('<input type="hidden" id="comment-pow-nonce" name="comment_pow_nonce" value="" />');
        $form.append('<input type="hidden" id="comment-pow-challenge" name="comment_pow_challenge" value="" />');
        $form.append('<input type="hidden" id="comment-pow-unique-str" name="comment_pow_unique_str" value="" />');
        $form.append('<input type="hidden" id="comment-pow-timestamp" name="comment_pow_timestamp" value="" />');
        $form.append('<input type="hidden" id="comment-pow-digest" name="comment_pow_digest" value="" />');

        // Add overlay and status elements
        $('body').append(`
            <div id="comment-hash-overlay" style="display: none;">
                <div class="comment-hash-modal">
                    <div class="comment-hash-spinner"></div>
                    <div id="comment-hash-status">Verifying comment submission...</div>
                    <div id="comment-hash-progress"></div>
                </div>
            </div>
        `);

        // Setup the form handler
        setupFormHandler();
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#commentform').length) {
            setupCommentForm();
        }
    });

})(jQuery);
