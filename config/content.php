<?php

/*
|--------------------------------------------------------------------------
| Public marketing copy
|--------------------------------------------------------------------------
|
| The words on the public site, kept here rather than in Blade so they can be
| rewritten without touching markup, and so the FAQ can feed both the visible
| accordion and the FAQPage structured data from one source.
|
| ---------------------------------------------------------------------------
| PLACEHOLDER CONTENT
| ---------------------------------------------------------------------------
|
| This is written to be plausible and structurally correct, not factual. Before
| this site goes anywhere near a search engine, every one of these has to be
| replaced with something true about the actual property:
|
|   - the address, phone number and coordinates in config/hotel.php
|   - the neighbourhood list below (distances and travel times)
|   - the amenity claims, the room descriptions and the photos
|   - the star rating, if the property has an official classification
|
| Publishing invented specifics is worse than publishing less: it misleads
| guests, and inconsistency between the site, the Google Business Profile and
| the listing sites is what actually sinks local rankings.
|
| ---------------------------------------------------------------------------
| Policy tokens
| ---------------------------------------------------------------------------
|
| Any :token below is replaced at render time from the *current* policy version
| by App\Support\SiteContent. This matters: the cancellation terms quoted to a
| visitor come from the same row the booking engine enforces, so the marketing
| copy cannot drift away from what the system actually does when an admin
| changes a setting.
|
| Available tokens: :hotel :city :district :phone :email :packages
| :buffer_minutes :hold_minutes :downpayment_percent :full_refund_hours
| :partial_refund_hours :grace_minutes :reschedule_hours :cheapest_price
|
*/

return [

    'hero' => [
        // The H1. One per page, carrying the primary keyword and the location,
        // because "hourly hotel" alone is a global query and this is one
        // property on one street.
        'heading' => 'Hourly hotel rooms in :district, :city',

        'subheading' => 'Pay for the hours you actually need. Rooms in 3, 6, 12 and 22-hour packages, '
            .'starting at :cheapest_price — no overnight minimum, no charge for time you will spend elsewhere.',

        'primary_cta' => 'Check availability',
        'secondary_cta' => 'See the rooms',
    ],

    /*
     * The opening body copy. Two things are happening here: it answers the
     * question a visitor arrives with ("what is this and is it for me"), and
     * it gives a search engine enough unique prose to understand the page.
     * Thin homepages that are all photographs and a booking widget give
     * nothing to rank.
     */
    'intro' => [
        'heading' => 'A hotel that charges by the hour, not by the night',

        'paragraphs' => [
            ':hotel is a small hotel in :district built around a simple observation: most '
            .'people who need a room do not need it for twenty-four hours. A flight lands at dawn and '
            .'the meeting is at eleven. A shift ends at two in the morning and the commute home is '
            .'ninety minutes each way. A quiet room with a desk and a door that locks is worth more '
            .'than a bed you will occupy for five hours and pay a full night for.',

            'So we sell time. You choose a start hour and a package — three, six, twelve or '
            .'twenty-two hours — and you pay for that and nothing else. The room is yours from the '
            .'minute your booking starts. There is no eleven-in-the-morning checkout to work around '
            .'and no late-arrival penalty, because there is no fixed night to be late for.',

            'Everything else is an ordinary good hotel. Rooms are cleaned between every single stay, '
            .'not every calendar day. Beds are proper beds. The walls are insulated and the curtains '
            .'are genuinely blackout, because roughly half our guests are trying to sleep in the '
            .'middle of the afternoon and that is a harder problem than sleeping at night.',
        ],
    ],

    /*
     * Short, scannable differentiators. Bullet-shaped content like this is what
     * gets lifted into AI-generated summaries, so each one is written to stand
     * alone as a complete answer rather than as a fragment.
     */
    'highlights' => [
        [
            'title' => 'Four lengths of stay',
            'body' => 'Three, six, twelve and twenty-two hours. The price is for the whole package, '
                .'not per hour, and it is the same price whether you book a week ahead or walk in.',
        ],
        [
            'title' => 'Every stay starts on the hour',
            'body' => 'Pick your start time and the room is ready. No queueing behind a standard '
                .'check-in time, and no waiting for someone else to leave.',
        ],
        [
            'title' => 'Cleaned between every stay',
            'body' => 'Each room gets :buffer_minutes minutes of turnover after every guest before it '
                .'can be booked again. We hold that time back rather than sell it.',
        ],
        [
            'title' => 'Free cancellation',
            'body' => 'Cancel more than :full_refund_hours hours ahead and you get everything back. '
                .'The terms shown when you book are the terms that apply, even if we change them later.',
        ],
        [
            'title' => 'Pay online or at the desk',
            'body' => 'Pay in full, pay :downpayment_percent% now and the rest on arrival, or settle the '
                .'whole thing at reception. Card details never touch our systems.',
        ],
        [
            'title' => 'Stay longer if the room is free',
            'body' => 'Ask the front desk to extend and we will check the room against the next booking. '
                .'If it is free, the extra hours are yours at the hourly rate.',
        ],
    ],

    'how_it_works' => [
        'heading' => 'How booking by the hour works',
        'steps' => [
            [
                'title' => 'Choose a date, a start hour and a length',
                'body' => 'The search shows only the rooms genuinely free for that exact window — '
                    .'including the cleaning time after the guest before you.',
            ],
            [
                'title' => 'Book and hold your room',
                'body' => 'A specific room is assigned the moment you book, not on arrival. If you are '
                    .'paying online, the room is held for :hold_minutes minutes while you do.',
            ],
            [
                'title' => 'Arrive and check in',
                'body' => 'Bring your booking reference and a valid ID. Arrive within :grace_minutes '
                    .'minutes of your start time — after that the room goes back on sale.',
            ],
        ],
    ],

    'rooms' => [
        'heading' => 'Rooms',
        'intro' => 'Four room types, eighteen rooms, all with the same soundproofing and the same '
            .'blackout curtains. Prices below are for the whole package.',
    ],

    'amenities' => [
        'heading' => 'What every room has',
        'intro' => 'No tiers of comfort. The difference between our room types is space and bedding, '
            .'not whether the essentials are included.',

        'groups' => [
            [
                'title' => 'For sleeping',
                'items' => [
                    'Genuine blackout curtains, not the token kind',
                    'Double-glazed windows and insulated walls',
                    'Individually controlled air conditioning',
                    'Fresh linen for every stay, not every night',
                ],
            ],
            [
                'title' => 'For working',
                'items' => [
                    'A full-size desk and a chair you can sit in for hours',
                    'Fibre Wi-Fi, 200 Mbps, no charge and no login page',
                    'Power outlets at the desk and both sides of the bed',
                    'Room lighting you can actually read by',
                ],
            ],
            [
                'title' => 'Practical',
                'items' => [
                    'Hot shower with unlimited hot water',
                    'Front desk staffed 24 hours',
                    'Luggage storage before and after your stay',
                    'Covered parking, charged by the hour',
                ],
            ],
        ],
    ],

    /*
     * Local content. This section is doing the heaviest SEO work on the page:
     * a search engine ranks a hotel for "hotel near X" partly on whether the
     * page credibly talks about X. It is also the section most likely to be
     * wrong, so it is the first one to replace with verified distances.
     */
    'neighbourhood' => [
        'heading' => 'Where we are',

        'intro' => ':hotel is on :street in :district, a walkable part of :city that stays awake late. '
            .'The area suits the way we sell rooms: it is close enough to the airport for a stopover '
            .'and close enough to the business districts for a working day.',

        'nearby' => [
            ['name' => 'Ninoy Aquino International Airport', 'detail' => 'about 8 km south, 20 to 40 minutes by car depending on traffic'],
            ['name' => 'Manila Bay and the baywalk', 'detail' => 'a 10-minute walk west'],
            ['name' => 'Intramuros and Rizal Park', 'detail' => 'about 2 km north'],
            ['name' => 'Makati central business district', 'detail' => 'about 7 km east'],
            ['name' => 'Robinsons Place Manila', 'detail' => 'a 12-minute walk'],
            ['name' => 'LRT-1 Pedro Gil station', 'detail' => 'a 6-minute walk'],
        ],

        'getting_here' => 'Taxis and ride-hailing drop off at the door. If you are arriving from the '
            .'airport, the ride is straightforward at any hour, which matters when your booking starts '
            .'at four in the morning.',
    ],

    'good_to_know' => [
        'heading' => 'Good to know before you book',
        'items' => [
            ['label' => 'Check-in', 'value' => 'Any hour, on the hour. Your room is ready when your booking starts.'],
            ['label' => 'ID required', 'value' => 'One valid government-issued ID per booking, at the desk.'],
            ['label' => 'Grace period', 'value' => ':grace_minutes minutes after your start time before the room is released.'],
            ['label' => 'Cleaning break', 'value' => ':buffer_minutes minutes between stays, held back from sale.'],
            ['label' => 'Extending', 'value' => 'Possible at the desk if the room is not booked after you.'],
            ['label' => 'Payment', 'value' => 'Online or at reception. Cash, card and bank transfer accepted in person.'],
        ],
    ],

    /*
     * FAQs. These earn their place three times over: they answer what people
     * actually ask, they are marked up as FAQPage for AI summaries and search
     * features, and every answer quotes the live policy rather than a number
     * someone typed once and forgot.
     */
    'faqs' => [
        [
            'question' => 'Can I really book a hotel room for just a few hours?',
            'answer' => 'Yes. :hotel sells rooms in 3, 6, 12 and 22-hour packages rather than by the '
                .'night. You choose the hour your stay starts and pay only for that block of time. There '
                .'is no overnight minimum and no requirement to check out at a fixed morning hour.',
        ],
        [
            'question' => 'How much does an hourly room cost?',
            'answer' => 'Prices start at :cheapest_price for the shortest package and vary by room type '
                .'and length of stay. The price you see is for the entire package, not per hour, and it '
                .'includes the room, Wi-Fi and every amenity — taxes are shown before you confirm.',
        ],
        [
            'question' => 'What time can I check in?',
            'answer' => 'Any hour of the day or night, on the hour. Because rooms are sold as time '
                .'intervals rather than nights, your room is prepared for the exact hour your booking '
                .'begins. Reception is staffed 24 hours.',
        ],
        [
            'question' => 'Can I book more than one stay on the same day?',
            'answer' => 'Yes, as long as the times do not overlap. You could take a room from 6am to '
                .'noon and another from 6pm to midnight. The booking system will tell you if two of your '
                .'bookings clash and name the one in the way.',
        ],
        [
            'question' => 'What is your cancellation policy?',
            'answer' => 'Cancel more than :full_refund_hours hours before your stay begins and you are '
                .'refunded in full. Between :partial_refund_hours and :full_refund_hours hours, the '
                .':downpayment_percent% downpayment is kept and anything above it is returned. Under '
                .':partial_refund_hours hours there is no refund. Whichever terms applied when you booked '
                .'are the terms we honour, even if we change the policy afterwards.',
        ],
        [
            'question' => 'What happens if I arrive late?',
            'answer' => 'You have :grace_minutes minutes after your start time. Arrive within that and '
                .'your room is waiting. After that the booking is marked a no-show, the room is released '
                .'to other guests and payment is forfeited. Call us if you are running late and we will '
                .'do what we can.',
        ],
        [
            'question' => 'Can I extend my stay once I have checked in?',
            'answer' => 'Ask the front desk. We check the room against the next booking, including the '
                .'cleaning time we hold after every stay. If it is free for the extra hours, the '
                .'extension is granted at the hourly rate and added to your bill. If someone is already '
                .'booked into the room, we will say no rather than move them.',
        ],
        [
            'question' => 'Do I have to pay online?',
            'answer' => 'No. You can pay in full online, pay a :downpayment_percent% downpayment and '
                .'settle the balance when you arrive, or pay the whole amount at reception. Online '
                .'bookings are held for :hold_minutes minutes while payment is completed; bookings paid '
                .'at the property are confirmed straight away.',
        ],
        [
            'question' => 'Are the rooms cleaned between guests?',
            'answer' => 'Every room is fully cleaned and re-linened after every stay, not once a day. '
                .'We hold :buffer_minutes minutes back after each booking specifically for this and '
                .'refuse to sell that time, which is why a room that looks free at first glance is '
                .'sometimes unavailable.',
        ],
        [
            'question' => 'Can I change my booking to a different time?',
            'answer' => 'One free move is included if you ask more than :reschedule_hours hours before '
                .'your stay and an equivalent room is free at the new time. Outside that window you can '
                .'cancel and rebook, which will price the new booking at current rates and apply the '
                .'cancellation terms to the old one.',
        ],
        [
            'question' => 'Is there parking?',
            'answer' => 'Yes, covered parking is available and charged by the hour. You can add it to '
                .'your booking or arrange it at the desk when you arrive.',
        ],
        [
            'question' => 'Do you take walk-ins?',
            'answer' => 'Yes. Reception is staffed around the clock and will book you into whatever is '
                .'free at that moment. Booking ahead is safer at night and at weekends, when the shorter '
                .'packages go quickly.',
        ],
    ],

    'closing_cta' => [
        'heading' => 'Find a room for the hours you need',
        'body' => 'Search live availability — no account required to look.',
        'button' => 'Check availability',
    ],
];
