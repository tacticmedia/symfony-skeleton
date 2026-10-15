import { FullStory, init as initFullStory } from '@fullstory/browser';

document.addEventListener("DOMContentLoaded", () => {
    if (document.querySelector('body')) {
        const data = document.querySelector('body').dataset || null;

        if (data.fullstoryId && data.userId) {
            initFullStory({orgId: data.fullstoryId});

            FullStory('setIdentity', {
                uid: data.userId,
                properties: {
                    displayName: data.userName,
                    email: data.userEmail
                }
            });
        }
    }
});





