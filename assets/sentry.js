import * as Sentry from "@sentry/browser";
import { BrowserTracing } from "@sentry/tracing";
import { CaptureConsole } from "@sentry/integrations";

Sentry.onLoad(function(){
    if (document.querySelector('body')) {
        const data = document.querySelector('body').dataset || null;

        if (data.sentryDsn) {
            Sentry.init({
                dsn: data.sentryDsn,
                release: data.release,
                environment: data.environment,
                integrations: [
                    new BrowserTracing(),
                    new CaptureConsole()
                ],
                tracesSampleRate: 0.5,
            });

            if (data.userId) {
                Sentry.setUser({
                    id: data.userId,
                    email: data.userEmail,
                });
            }
        }
    }
});

