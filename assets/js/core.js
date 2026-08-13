var CMSInterwaly = {}; 	//interwaly odświeżania okien

(function($)
{
	//dodaje rozszerzenie do jQuery, że odgrywa melodię z linku
    $.extend({playSound: function(){return $("<audio autoplay='autoplay' style='display:none;' controls='controls'><source src='"+arguments[0]+"' /></audio>").appendTo('body');}});

})(jQuery);

function CMSInit()
{
    // Rejestracja Service Workera dla PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
    }
    
    let windows = CMSGetParameterByName('CMSWindows');
    
    if (windows == null) {
        // Strzał do nowej trasy /intro z wyraznym parametrem render
        $.ajax({
            url: "/intro?render=intro",
            success: function(e) {
                // Wstrzykujemy czysty HTML powitania do kontenera #CMSIntro
                $("#CMSIntro").html(e).show();
            },
            error: function(xhr, status, error) {
                console.error('Błąd ładowania Intro:', error);
            }
        });
    } else {
        // Jeśli mamy zapamiętane okna w URL, ukrywamy intro i odpalamy okna
        $('#CMSIntro').hide();
        CMSWindowsHide();
        
        try {
            JSON.parse(windows).forEach(w => CMSWindowShow(w[0], w[1], w[2] ?? {}));
        } catch(e) { 
            console.log('CMSWindows parse error:', e, windows); 
        }
        
        history.replaceState(null, '', window.location.pathname);
    }
}

function CMSReLoad()
{
		//funkcja przeładowuje podstrony po zalogowaniu
	$.ajax({url: "/cmsmenu", success: function(e) {$("#CMSMenu").html(e);}});

	$.ajax({url: "/ui", success: function(e) {$("#CMSUI").html(e);}});

	CMSLoaderHide();
}

function CMSLogout()
{
		//funkcja wylogowuje

	$.ajax({url: "process/CMSLogout.ajax.php",
		success: function(result)
		{
//				console.log(result);
				CMSReLoad();	//whole site reload due to login
		}});

}

function CMSLoaderShow()
{
	//functions shows loader
	$("#CMSLoader").show();
//	console.log('show loader');
}

function CMSLoaderHide()
{
	//function hides loader
	$("#CMSLoader").hide();
//	console.log('hide loader');
}

function CMSWindowShow(window, page, args = {})
{
    CMSWindowHide('Over'); // Schowanie okna Over

    // Budujemy URL: nazwa podstrony + render=window
    const params = new URLSearchParams(args);
    params.set('render', 'window');

    const url = `/${page}?${params.toString()}`;

    $.ajax({
        url: url,
        success: function(e) {
            $("#CMSWindow" + window).html(e);
        }
    });

    $("#CMSShadow").fadeIn(500);
    $("#CMSWindow" + window).fadeIn(500);
}

function CMSWindowHide(window)
{
	//function hides window
	$("#CMSWindow"+window).fadeOut(500);
}

function CMSWindowsHide()
{
		//function hides windows
	$("#CMSWindowCenter").fadeOut(500);
	$("#CMSWindowTop").fadeOut(500);
	$("#CMSWindowBottom").fadeOut(500);
	$("#CMSWindowLeft").fadeOut(500);
	$("#CMSWindowRight").fadeOut(500);
	$("#CMSWindowOver").fadeOut(500);
	$("#CMSShadow").fadeOut(500);
}

function CMSNoticeShow(html)
{
	//funkcja pokazuje komunikat na dole strony
	$("#CMSNotice").html(html);
	$("#CMSNotice").fadeIn(500).delay(3000).fadeOut(1500);
}

function CMSOrientuj()
{
    // Jeśli ekran jest wyższy niż szerszy, ustawia układ pionowy (portrait), w przeciwnym razie poziomy (landscape)
    if ($(window).height() > $(window).width()) {
        CMSReOrientuj('portrait');
    } else {
        CMSReOrientuj('landscape');
    }
}

function CMSReOrientuj(kierunek = 'reOrientuj')
{   
    let url = "/action/cmsupdate?tryb=" + kierunek;

    // Jeśli przekazano konkretny układ ('portrait' lub 'landscape'), mapujemy to na setOrientation&value=...
    if (kierunek === 'portrait' || kierunek === 'landscape') {
        url = "/action/cmsupdate?tryb=setOrientation&value=" + kierunek;
    }

    $.ajax({
        url: url,
        dataType: "json",
        success: function(response) {
//            console.log("Orientacja:", response.data?.orientation); 
            CMSReLoad();
        }
    });
}

function CMSLoginGoogleZaloguj(wynik)
{
    // Generuje link do zalogowania googlem
    $.ajax({
        url: "action/login?typ=google&dane=" + encodeURIComponent(wynik.credential),
        dataType: "json",
        success: function(result)
        {
            if (result && result.error) {
                alert(result.error);
            } else {
                CMSReLoad();
            }
        }
    });
}

function CMSLoginGoogleInicjuj(selector, CID)
{
    //funkcja inicjuje powstanie odpowiedniego guzika google w odpowiednim miejscu
    try
    {
        google.accounts.id.initialize({client_id: CID, callback: CMSLoginGoogleZaloguj});
        
        // TUTAJ dodajemy parametry odpowiedzialne za pełną szerokość:
        google.accounts.id.renderButton(
            document.querySelector(selector),   
            { 
                theme: "outline", 
                size: "large",
                width_type: "filled", // Informuje Google, że ma wypełnić przestrzeń
                width: "100%"         // Wymusza 100% szerokości
            } 
        );
    }
    catch(e) {console.log('Problem z załadowaniem guzika loginGoogle:', e);}
}

function CMSLoginGoogle(selector, CID)
{
		//funkcja obsługuje wstawienie guzika logowania google
	if (typeof google !== 'undefined' && google.accounts) CMSLoginGoogleInicjuj(selector, CID);
	else
	{
		window.addEventListener('load', function()
		{
			if (typeof google !== 'undefined' && google.accounts) CMSLoginGoogleInicjuj(selector, CID);
			else
			{
				var checkInterval = setInterval(function()
				{
					if (typeof google !== 'undefined' && google.accounts)
					{
						clearInterval(checkInterval);
						CMSLoginGoogleInicjuj(selector, CID);
					}
				}, 100);
				
				setTimeout(function(){clearInterval(checkInterval);}, 5000);
			}
		});
	}
}

function CMSGetParameterByName(name, url)
{
    // funkcja zwraca wartość konkretnego parametru z URL
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function CMSQueryStringToJSON(url)
{
	//Konwertuje cały query string na obiekt JSON z wszystkimi parametrami.
    if (url === '') return '';
    var pairs = (url || location.search).slice(1).split('&');
    var result = {};
    for (var idx in pairs) {
        var pair = pairs[idx].split('=');
        if (!!pair[0])
            result[pair[0].toLowerCase()] = decodeURIComponent(pair[1] || '');
    }
    return result;
}

function CMSAkcja(link, callback=null)
{
	// wstawi efekt z linku w div, po zakończeniu może wywołać callback
	$.ajax(
	{
		url: link,
		success: function(html)
		{
			if (typeof callback === 'function') {
				callback(html);
			}
		}
	});
}

function CMSDivAktualizuj(link, div, callback=null)
{
	// wstawi efekt z linku w div, po zakończeniu może wywołać callback
	$.ajax(
	{
		url: link,
		success: function(html)
		{
			$(div).html(html);
			if (typeof callback === 'function') {
				callback(html);
			}
		}
	});
}

function CMSDivAkcjaAktualizuj(wykonaj, link, div, callback=null, czyKomunikat=false)
{
	//wykona wykonaj, a potem wczyta wynik link do div, na koniec ewentualnie wykona callback i ewentualnie wynik przekaże też do komunikatu
    $.ajax({
        url: wykonaj,
        success: function(html)
        {
            CMSDivAktualizuj(link, div, function() {
                if (typeof callback === 'function') {
                    callback(html);
                }
                if (czyKomunikat === true) {
                    CMSNoticeShow(html);
                }
            });
        }
    });
}

function CMSWykresGeneruj(kontener, dane)
{
    if (!kontener) return;

    // Jeśli przekazano DIV, utwórz w nim CANVAS i wymuś pełny wymiar parenta
    let canvas = kontener;
    if (kontener.tagName !== 'CANVAS') {
        kontener.innerHTML = ''; 
        canvas = document.createElement('canvas');
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.display = 'block';
        kontener.appendChild(canvas);
    }
    
    function rysuj() {
        if (dane.data?.datasets) {
            dane.data.datasets.forEach(d => {
                if (d.data?.[0]?.x && typeof d.data[0].x === 'string') {
                    d.data = d.data.map(p => ({x: new Date(p.x), y: p.y}));
                }
            });
        }
        
        // Zapewniamy responsywność Chart.js do okna
        if (!dane.options) dane.options = {};
        dane.options.responsive = true;
        dane.options.maintainAspectRatio = false;
        
        return new Chart(canvas, {type: 'line', data: dane.data, options: dane.options});
    }
    
    const needsAdapter = Object.values(dane.options?.scales || {}).some(s => s.type === 'time');
    if (needsAdapter && !window._chartAdapter) {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3';
        s.onload = () => { window._chartAdapter = 1; rysuj(); };
        document.head.appendChild(s);
    } else {
        return rysuj();
    }
}

function CMSCzasKonwertuj(sourceTimezone, time) {
    return luxon.DateTime.fromISO(time, { zone: sourceTimezone })
        .toLocal()
        .toFormat('HH:mm:ss');
}

function CMSOknoInterwal(nazwa, czas)
{
    // Usuń stary interwał o tej nazwie, jeśli istniał
    if (CMSInterwaly[nazwa]) {
        clearInterval(CMSInterwaly[nazwa]);
    }

    // Nowy, czysty URL zgodny z Routerem: /performance?render=widget
    const url = '/' + nazwa + '?render=widget';

    // Pierwsze pobranie treści kafelka
    CMSDivAktualizuj(url, '#' + nazwa);

    // Ustawienie interwału odświeżania
    CMSInterwaly[nazwa] = setInterval(() => CMSDivAktualizuj(url, '#' + nazwa), czas);

    return CMSInterwaly[nazwa];
}

