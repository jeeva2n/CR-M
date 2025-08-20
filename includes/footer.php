    </main>
    <footer>
        <p>&copy; <?= date('Y') ?>  ✔</p>
    </footer>

    <!-- START: JavaScript for Toast Notification -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find the toast element in the page
            const toast = document.getElementById('toast-notification');

            // If the toast element exists...
            if (toast) {
                // ...wait a tiny moment, then add the 'show' class to trigger the animation
                setTimeout(() => {
                    toast.classList.add('show');
                }, 100);

                // ...wait 3 seconds, then remove the 'show' class to hide it again
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        });
    </script>
    <!-- END: JavaScript -->

</body>
</html>