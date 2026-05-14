        </div><!-- /.page-content -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
    // Live Clock
    function updateClock() {
        const now = new Date();
        const options = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
        const el = document.getElementById('navClock');
        if (el) el.textContent = now.toLocaleTimeString([], options);
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>
</body>
</html>