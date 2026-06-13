<?php

declare(strict_types=1);

namespace App\Entity;

final class AdminSettingsDefaults
{
    public const LEGAL_NOTICE = <<<'MARKDOWN'
        # Legal Notice (Impressum)

        > [!WARNING]
        > Please adapt this template to align with your company's actual legal details.

        **Company Name:** [Company Name]  
        **Representative:** [Representative Name]  
        **Address:** [Street & Number], [Zip Code & City]  
        **Email:** [Email Address]  
        **Phone:** [Phone Number]  

        *Disclaimer: This is a placeholder text to demonstrate markdown formatting. Replace it with your official legal notice.*
        MARKDOWN;

    public const TERMS_AND_CONDITIONS = <<<'MARKDOWN'
        # Terms and Conditions (AGB)

        > [!WARNING]
        > Please adapt these terms and conditions to align with your company's actual membership rules and policies.

        ### 1. General Rules
        Members are required to follow the safety rules of our gym.

        ### 2. Bookings & Cancellations
        All class booking cancellations must be made at least 24 hours prior to the class start time.

        ### 3. Liability
        We do not take responsibility for lost items or personal injuries.
        MARKDOWN;

    public const WELCOME_MAIL = <<<'MARKDOWN'
        # Welcome to {company_name}!

        > [!WARNING]
        > Please adapt this template to align with your company's actual communication style.

        Hello {user_name},

        We are thrilled to welcome you to our community! You have successfully registered your account.

        You can now view our schedule and book your classes directly through the app.

        Best regards,  
        The {company_name} Team
        MARKDOWN;

    public const MEMBERSHIP_WELCOME_MAIL = <<<'MARKDOWN'
        # Welcome to your Membership at {company_name}!

        > [!WARNING]
        > Please adapt this template to align with your company's actual communication style.

        Hello {user_name},

        Thank you for choosing {company_name}! Your membership has been activated.

        We look forward to helping you achieve your fitness goals.

        Best regards,  
        The {company_name} Team
        MARKDOWN;
}
